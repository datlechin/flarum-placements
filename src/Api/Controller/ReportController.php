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
use Datlechin\Placements\Model\Stat;
use Datlechin\Placements\Support\Permissions;
use Flarum\Http\RequestUtil;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;
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
     * The daily series, as a file.
     */
    protected function csv(Carbon $since, int $days): ResponseInterface
    {
        $lines = ['date,impressions,viewable,clicks'];

        foreach ($this->daily($since) as $row) {
            // Quoted, because a campaign name is not in this file but a date
            // format that changes could still be.
            $lines[] = $row['day'].','.$row['impressions'].','.$row['viewable'].','.$row['clicks'];
        }

        return new TextResponse(implode("\n", $lines)."\n", 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="placement-'.$days.'-days.csv"',
        ]);
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
        $rows = $this->query($since)
            ->select(new Expression("substr(bucket_start, 1, 10) as day"))
            ->selectRaw('SUM(impressions) as impressions, SUM(viewable_impressions) as viewable, SUM(clicks) as clicks')
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        return array_values($rows->map(fn (object $row) => [
            'day' => is_scalar($row->day) ? (string) $row->day : '',
            'impressions' => is_numeric($row->impressions) ? (int) $row->impressions : 0,
            'viewable' => is_numeric($row->viewable) ? (int) $row->viewable : 0,
            'clicks' => is_numeric($row->clicks) ? (int) $row->clicks : 0,
        ])->all());
    }

    /**
     * @return list<array{key: string, impressions: int, viewable: int, clicks: int}>
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

        return array_values($rows->map(fn (object $row) => [
            'key' => is_scalar($row->{$column}) ? (string) $row->{$column} : '',
            'impressions' => is_numeric($row->impressions) ? (int) $row->impressions : 0,
            'viewable' => is_numeric($row->viewable) ? (int) $row->viewable : 0,
            'clicks' => is_numeric($row->clicks) ? (int) $row->clicks : 0,
        ])->all());
    }
}
