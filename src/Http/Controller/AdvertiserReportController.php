<?php

/*
 * This file is part of datlechin/flarum-placements.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\Placements\Http\Controller;

use Carbon\Carbon;
use Datlechin\Placements\Measurement\Recorder;
use Datlechin\Placements\Model\Advertiser;
use Datlechin\Placements\Model\Stat;
use Flarum\Settings\SettingsRepositoryInterface;
use Laminas\Diactoros\Response\HtmlResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tobyz\JsonApiServer\Exception\NotFoundException;

/**
 * What an advertiser gets instead of a login.
 *
 * The thing an advertiser actually wants is not to edit their campaign; it is
 * to know they are getting what they paid for without emailing the forum every
 * week. Flarum's admin area is gated by a single all-or-nothing `administrate`
 * permission, so "give them a login" means either handing a stranger the whole
 * forum or building a second complete frontend with its own routes, policies,
 * scoped endpoints and upload handling — a three-month product bolted onto a
 * three-week one, and the exact thing that killed the last extension to try.
 *
 * This is a page at an unguessable URL that shows their delivery and nothing
 * else. It is deliberately plain HTML rather than the forum's own frontend: it
 * has no session, nothing to boot, and nothing on it that could accidentally
 * carry the forum's payload to somebody outside the forum.
 *
 * It shows delivery only. Never targeting, never rates, and never another
 * advertiser.
 */
class AdvertiserReportController implements RequestHandlerInterface
{
    public const DAYS = 90;

    public function __construct(
        protected Recorder $recorder,
        protected SettingsRepositoryInterface $settings,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $parameters = $request->getAttribute('routeParameters');
        $token = is_array($parameters) ? ($parameters['token'] ?? null) : null;

        $advertiser = is_string($token) && $token !== ''
            ? Advertiser::query()->where('report_token', $token)->first()
            : null;

        // A revoked or expired token is not found rather than forbidden: the
        // difference tells somebody guessing that they got the length right.
        if (! $advertiser instanceof Advertiser || ! $advertiser->hasUsableReportToken()) {
            throw new NotFoundException();
        }

        $this->recorder->flush();

        return new HtmlResponse($this->page($advertiser), 200, [
            // The URL is the credential, so it must not end up in an index.
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
            'Referrer-Policy' => 'no-referrer',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /**
     * @return list<array{name: string, impressions: int, viewable: int, clicks: int}>
     */
    protected function rows(Advertiser $advertiser): array
    {
        $since = Carbon::now()->utc()->startOfHour()->subDays(self::DAYS);

        $rows = Stat::query()
            ->toBase()
            ->join('placement_campaigns', 'placement_campaigns.id', '=', 'placement_stats.campaign_id')
            ->where('placement_campaigns.advertiser_id', $advertiser->id)
            ->where('placement_stats.bucket_start', '>=', $since)
            ->groupBy('placement_campaigns.id', 'placement_campaigns.name')
            ->select('placement_campaigns.name')
            ->selectRaw('SUM(placement_stats.impressions) as impressions, SUM(placement_stats.viewable_impressions) as viewable, SUM(placement_stats.clicks) as clicks')
            ->orderByDesc('impressions')
            ->get();

        return array_values($rows->map(fn (object $row) => [
            'name' => is_scalar($row->name) ? (string) $row->name : '',
            'impressions' => is_numeric($row->impressions) ? (int) $row->impressions : 0,
            'viewable' => is_numeric($row->viewable) ? (int) $row->viewable : 0,
            'clicks' => is_numeric($row->clicks) ? (int) $row->clicks : 0,
        ])->all());
    }

    protected function page(Advertiser $advertiser): string
    {
        $stored = $this->settings->get('forum_title');
        $forum = is_string($stored) && $stored !== '' ? $stored : 'Forum';
        $rows = $this->rows($advertiser);

        $body = $rows === []
            ? '<p class="empty">Nothing has been delivered in the last '.self::DAYS.' days.</p>'
            : $this->table($rows);

        return $this->shell($this->escape($advertiser->name).' &middot; '.$this->escape($forum), $body);
    }

    /**
     * @param  list<array{name: string, impressions: int, viewable: int, clicks: int}>  $rows
     */
    protected function table(array $rows): string
    {
        $cells = '';

        foreach ($rows as $row) {
            $impressions = $row['impressions'];

            $cells .= '<tr>'
                .'<td>'.$this->escape($row['name']).'</td>'
                .'<td class="n">'.number_format($impressions).'</td>'
                .'<td class="n">'.$this->rate($row['viewable'], $impressions).'</td>'
                .'<td class="n">'.number_format($row['clicks']).'</td>'
                .'<td class="n">'.$this->rate($row['clicks'], $impressions).'</td>'
                .'</tr>';
        }

        return '<table><thead><tr><th>Campaign</th><th class="n">Impressions</th>'
            .'<th class="n">Viewable</th><th class="n">Clicks</th><th class="n">Click-through</th>'
            .'</tr></thead><tbody>'.$cells.'</tbody></table>';
    }

    /**
     * A rate, or a dash when there is not enough to divide by.
     *
     * One click on eight impressions is not a 12.5% click-through rate, and
     * printing it as one is how a number nobody should quote gets quoted.
     */
    protected function rate(int $part, int $whole): string
    {
        return $whole < 1000 ? '&mdash;' : number_format($part / $whole * 100, 1).'%';
    }

    protected function shell(string $title, string $body): string
    {
        return <<<HTML
            <!doctype html>
            <html lang="en">
            <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <meta name="robots" content="noindex, nofollow, noarchive">
            <title>$title</title>
            <style>
            :root { color-scheme: light dark; }
            body { margin: 0; padding: 40px 20px; font: 16px/1.6 system-ui, sans-serif; }
            main { max-width: 720px; margin: 0 auto; }
            h1 { font-size: 20px; margin: 0 0 4px; }
            .since { color: #666; font-size: 14px; margin: 0 0 24px; }
            table { width: 100%; border-collapse: collapse; font-size: 14px; }
            th, td { padding: 8px 10px; border-bottom: 1px solid #8884; text-align: left; }
            th { font-size: 12px; text-transform: uppercase; letter-spacing: .04em; color: #666; }
            .n { text-align: right; font-variant-numeric: tabular-nums; }
            .empty { color: #666; }
            footer { margin-top: 30px; color: #666; font-size: 13px; }
            </style>
            </head>
            <body>
            <main>
            <h1>$title</h1>
            <p class="since">Delivery over the last {$this->days()} days.</p>
            $body
            <footer>Viewable means at least half the advert was on screen for a full second. Rates are hidden below 1,000 impressions, where they mean nothing.</footer>
            </main>
            </body>
            </html>
            HTML;
    }

    protected function days(): int
    {
        return self::DAYS;
    }

    protected function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
