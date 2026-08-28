<?php

/*
 * This file is part of datlechin/flarum-placements.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\Placements\Api\Controller;

use Carbon\Carbon;
use Datlechin\Placements\Measurement\Recorder;
use Datlechin\Placements\Model\Campaign;
use Datlechin\Placements\Model\Creative;
use Datlechin\Placements\Model\Stat;
use Datlechin\Placements\Support\Permissions;
use Flarum\Http\RequestUtil;
use Illuminate\Database\Query\Builder;
use Laminas\Diactoros\Response\JsonResponse;
use Laminas\Diactoros\Response\TextResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * What was delivered, summarised.
 *
 * Reads the hourly buckets rather than the running counters on campaigns: the
 * counters exist to answer "has this hit its cap?" without summing a year of
 * history, and they keep counting after old buckets are pruned. Mixing the two
 * would produce a report that disagrees with itself.
 */
class ReportController implements RequestHandlerInterface
{
    public const MAX_DAYS = 365;

    public const DEFAULT_DAYS = 30;

    public function __construct(protected Recorder $recorder)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        RequestUtil::getActor($request)->assertCan(Permissions::MANAGE);

        // Anything still buffered belongs in the answer. An administrator who
        // opens the reports a minute after a campaign went live should not be
        // told it delivered nothing.
        $this->recorder->flush();

        $days = $this->days($request);
        $since = Carbon::now()->utc()->startOfHour()->subDays($days);

        // A spreadsheet is what somebody reconciling an invoice actually wants,
        // and it is two dozen lines rather than a second endpoint.
        if (($request->getQueryParams()['format'] ?? null) === 'csv') {
            return $this->csv($since, $days);
        }

        return new JsonResponse([
            'since' => $since->toIso8601String(),
            'days' => $days,
            'totals' => $this->totals($since),
            'daily' => $this->daily($since),
            'campaigns' => $this->groupedBy($since, 'campaign_id'),
            'creatives' => $this->groupedBy($since, 'creative_id'),
            'placements' => $this->groupedBy($since, 'placement_key'),
        ]);
    }

    /**
     * The report, as a file.
     *
     * The daily series and then each breakdown, in one file with a `section`
     * column. Somebody reconciling an invoice needs the per-campaign numbers
     * most of all, and a forum-wide daily total -- which is all this used to
     * carry -- cannot answer a single question an advertiser asks.
     */
    protected function csv(Carbon $since, int $days): ResponseInterface
    {
        $lines = ['section,key,name,impressions,viewable,clicks'];

        foreach ($this->daily($since) as $row) {
            $lines[] = implode(',', ['day', $row['day'], '', $row['impressions'], $row['viewable'], $row['clicks']]);
        }

        foreach (['campaign' => 'campaign_id', 'creative' => 'creative_id', 'slot' => 'placement_key'] as $section => $column) {
            foreach ($this->groupedBy($since, $column) as $row) {
                $lines[] = implode(',', [
                    $section,
                    $this->quote($row['key']),
                    $this->quote($row['name']),
                    $row['impressions'],
                    $row['viewable'],
                    $row['clicks'],
                ]);
            }
        }

        return new TextResponse(implode("\n", $lines)."\n", 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="placements-'.$days.'-days.csv"',
        ]);
    }

    /**
     * A CSV field. Campaign names are written by people and contain commas,
     * quotes and newlines; a file that breaks on the first one of those is
     * worse than no file.
     */
    protected function quote(string $value): string
    {
        return '"'.str_replace('"', '""', $value).'"';
    }

    protected function days(ServerRequestInterface $request): int
    {
        $requested = $request->getQueryParams()['days'] ?? null;

        if (! is_numeric($requested)) {
            return self::DEFAULT_DAYS;
        }

        return max(1, min(self::MAX_DAYS, (int) $requested));
    }

    protected function query(Carbon $since): Builder
    {
        return Stat::query()->where('bucket_start', '>=', $since)->toBase();
    }

    /**
     * @return array<string, int>
     */
    protected function totals(Carbon $since): array
    {
        $row = $this->query($since)
            ->selectRaw('SUM(impressions) as impressions, SUM(viewable_impressions) as viewable, SUM(clicks) as clicks, SUM(filtered) as filtered')
            ->first();

        return [
            'impressions' => (int) ($row->impressions ?? 0),
            'viewable' => (int) ($row->viewable ?? 0),
            'clicks' => (int) ($row->clicks ?? 0),
            // Never hidden. An advertiser asking why their impressions dropped
            // by a fifth deserves an answer, and a number that quietly vanished
            // is not one.
            'filtered' => (int) ($row->filtered ?? 0),
        ];
    }

    /**
     * One point per day, for the chart.
     *
     * Days rather than hours: an hourly series over a month is 720 points on a
     * chart a few hundred pixels wide, which is noise rather than information.
     *
     * @return list<array{day: string, impressions: int, viewable: int, clicks: int}>
     */
    protected function daily(Carbon $since): array
    {
        // Grouped by the hour in SQL and folded into days here, rather than
        // asking the database for the date part.
        //
        // There is no portable way to write that part. `substr(bucket_start,
        // 1, 10)` reads the datetime as text, which SQLite and MySQL allow by
        // implicit conversion and PostgreSQL refuses outright -- there is no
        // `substr(timestamp, integer, integer)`. `DATE()`, `CAST(... AS DATE)`
        // and `to_char()` each work on some drivers and not others, so the
        // alternative is branching on the connection's driver name in a
        // report.
        //
        // Folding here costs nothing. The rows are already summed across every
        // campaign, creative, slot and device, so what comes back is one row
        // per hour in the window: 720 for a month, 8,760 for a year, whatever
        // the size of the forum. `bucket_start` is the leading column of the
        // unique index, so the grouping is answered by it.
        $rows = $this->query($since)
            ->select('bucket_start')
            ->selectRaw('SUM(impressions) as impressions, SUM(viewable_impressions) as viewable, SUM(clicks) as clicks')
            ->groupBy('bucket_start')
            ->orderBy('bucket_start')
            ->get();

        $days = [];

        foreach ($rows as $row) {
            // Drivers hand this back differently -- a string on MySQL and
            // SQLite, a string PostgreSQL formats its own way -- so it is
            // parsed rather than sliced.
            $day = Carbon::parse((string) $row->bucket_start)->format('Y-m-d');

            $days[$day] ??= ['day' => $day, 'impressions' => 0, 'viewable' => 0, 'clicks' => 0];

            $days[$day]['impressions'] += is_numeric($row->impressions) ? (int) $row->impressions : 0;
            $days[$day]['viewable'] += is_numeric($row->viewable) ? (int) $row->viewable : 0;
            $days[$day]['clicks'] += is_numeric($row->clicks) ? (int) $row->clicks : 0;
        }

        // Ordered by the query, and insertion order preserves it.
        return array_values($days);
    }

    /**
     * @return list<array{key: string, name: string, impressions: int, viewable: int, clicks: int}>
     */
    protected function groupedBy(Carbon $since, string $column): array
    {
        $rows = $this->query($since)
            ->select($column)
            ->selectRaw('SUM(impressions) as impressions, SUM(viewable_impressions) as viewable, SUM(clicks) as clicks')
            ->groupBy($column)
            ->orderByDesc('impressions')
            ->limit(100)
            ->get();

        $names = $this->namesFor($column, array_values($rows->pluck($column)->all()));

        return array_values($rows->map(function (object $row) use ($column, $names): array {
            $key = is_scalar($row->{$column}) ? (string) $row->{$column} : '';

            return [
                'key' => $key,
                // The name the row is known by, or the key when it has none.
                // A report that prints `7` where a campaign name belongs
                // cannot be read by the person who has to act on it, and the
                // advertiser's own page has always joined the names.
                'name' => $names[$key] ?? $key,
                'impressions' => is_numeric($row->impressions) ? (int) $row->impressions : 0,
                'viewable' => is_numeric($row->viewable) ? (int) $row->viewable : 0,
                'clicks' => is_numeric($row->clicks) ? (int) $row->clicks : 0,
            ];
        })->all());
    }

    /**
     * Names for the ids just grouped on, in one query.
     *
     * A slot is not a row anywhere -- it is declared in code -- so its key is
     * looked up in the registry instead, and its label is a translation key
     * the client resolves.
     *
     * The key type is `int|string` and cannot be narrowed: PHP turns a
     * numeric string key into an int on the way in, so `['7' => 'Acme']` is
     * really `[7 => 'Acme']`. The lookup coerces the same way, so reading it
     * back with the string `'7'` finds it -- but saying `array<string, string>`
     * here would be a lie about what the array holds.
     *
     * @param  list<mixed>  $keys
     * @return array<int|string, string>
     */
    protected function namesFor(string $column, array $keys): array
    {
        $ids = array_values(array_filter(array_map('intval', array_filter($keys, 'is_numeric'))));

        $model = match ($column) {
            'campaign_id' => Campaign::class,
            'creative_id' => Creative::class,
            default => null,
        };

        if ($model === null || $ids === []) {
            return [];
        }

        $names = [];

        foreach ($model::query()->whereIn('id', $ids)->get(['id', 'name']) as $row) {
            $names[(string) $row->id] = (string) $row->name;
        }

        return $names;
    }
}
