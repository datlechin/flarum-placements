<?php

/*
 * This file is part of datlechin/flarum-placements.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\Placements\Tests\integration;

use Carbon\Carbon;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * The report, read off seeded buckets rather than off the beacon.
 *
 * RecordsEventsTest already covers the round trip from a beacon to a bucket.
 * What is missing there is a window with more than one bucket in it, which is
 * the only shape that shows whether hours are folded into days correctly --
 * with a single bucket, every grouping produces one point and every
 * implementation looks right.
 */
class ReportsDeliveryTest extends TestCase
{
    use RetrievesAuthorizedUsers;
    use SeedsCampaigns;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('datlechin-placements');

        $this->prepareDatabase([
            'users' => [$this->normalUser()],
            'group_permission' => [],
            ...$this->campaignSeed(),
        ]);
    }

    /**
     * Buckets across two days, several hours each, and two slots within one
     * hour so that a single day has rows that must be summed rather than
     * merely counted.
     *
     * Placed relative to now, because the report only looks back a fixed
     * number of days.
     */
    private function seedBuckets(): void
    {
        $day = Carbon::now()->utc()->startOfDay()->subDays(2);

        $rows = [];
        $id = 0;

        foreach ([[0, 9, 'index_above_list', 5], [0, 9, 'index_sidebar', 3], [0, 14, 'index_above_list', 2], [1, 11, 'index_above_list', 7]] as [$offset, $hour, $key, $impressions]) {
            $rows[] = [
                'id' => ++$id,
                'bucket_start' => $day->copy()->addDays($offset)->addHours($hour)->format('Y-m-d H:i:s'),
                'campaign_id' => 1,
                'creative_id' => 1,
                'placement_key' => $key,
                'device' => 'desktop',
                'impressions' => $impressions,
                'viewable_impressions' => 1,
                'clicks' => 1,
                'filtered' => 0,
            ];
        }

        $this->database()->table('placement_stats')->insert($rows);
    }

    /**
     * @return array<string, mixed>
     */
    private function report(): array
    {
        $response = $this->send($this->request('GET', '/api/placements/report', ['authenticatedAs' => 1]));

        $this->assertSame(200, $response->getStatusCode());

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * Three buckets on the first day, one on the second. Anything that
     * groups by the hour, or fails to sum within a day, gets a different
     * answer.
     */
    #[Test]
    public function the_daily_series_has_one_point_per_day(): void
    {
        $this->seedBuckets();

        $daily = $this->report()['daily'];

        $this->assertCount(2, $daily);
        $this->assertSame(10, $daily[0]['impressions']);
        $this->assertSame(7, $daily[1]['impressions']);
    }

    #[Test]
    public function the_daily_series_runs_oldest_first(): void
    {
        $this->seedBuckets();

        $days = array_column($this->report()['daily'], 'day');

        $this->assertSame([
            Carbon::now()->utc()->startOfDay()->subDays(2)->format('Y-m-d'),
            Carbon::now()->utc()->startOfDay()->subDay()->format('Y-m-d'),
        ], $days);
    }

    /**
     * The day is a plain calendar date, whatever shape the driver hands the
     * timestamp back in. PostgreSQL returns it formatted differently from
     * MySQL and SQLite, which is why it is parsed rather than sliced.
     */
    #[Test]
    public function each_point_is_labelled_with_a_calendar_date(): void
    {
        $this->seedBuckets();

        foreach ($this->report()['daily'] as $point) {
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $point['day']);
        }
    }

    /**
     * The totals are computed independently of the series, so they are worth
     * checking against it: a report that disagrees with its own chart is
     * worse than one that is merely wrong.
     */
    #[Test]
    public function the_totals_agree_with_the_series(): void
    {
        $this->seedBuckets();

        $body = $this->report();

        $this->assertSame(17, $body['totals']['impressions']);
        $this->assertSame(array_sum(array_column($body['daily'], 'impressions')), $body['totals']['impressions']);
    }

    /**
     * Grouping by slot must not be confused by two slots sharing an hour.
     */
    #[Test]
    public function grouping_by_slot_sums_across_hours(): void
    {
        $this->seedBuckets();

        $byKey = array_column($this->report()['placements'], 'impressions', 'key');

        $this->assertSame(14, $byKey['index_above_list']);
        $this->assertSame(3, $byKey['index_sidebar']);
    }

    /**
     * @return list<string>
     */
    private function csv(): array
    {
        $response = $this->send($this->request('GET', '/api/placements/report', [
            'authenticatedAs' => 1,
        ])->withQueryParams(['format' => 'csv']));

        $this->assertStringStartsWith('text/csv', $response->getHeaderLine('Content-Type'));

        return explode("\n", trim((string) $response->getBody()));
    }

    #[Test]
    public function the_csv_carries_the_daily_series(): void
    {
        $this->seedBuckets();

        $lines = $this->csv();
        $days = array_values(array_filter($lines, fn (string $line): bool => str_starts_with($line, 'day,')));

        $this->assertSame('section,key,name,impressions,viewable,clicks', $lines[0]);
        $this->assertCount(2, $days);
        $this->assertStringEndsWith(',10,3,3', $days[0]);
        $this->assertStringEndsWith(',7,1,1', $days[1]);
    }

    /**
     * The breakdown is the reason somebody downloads this at all. A
     * forum-wide daily total, which is all the file used to hold, answers no
     * question an advertiser asks.
     */
    #[Test]
    public function the_csv_carries_the_breakdowns_too(): void
    {
        $this->seedBuckets();

        $lines = $this->csv();

        $this->assertContains('campaign,"1","Acme",17,4,4', $lines);
        $this->assertContains('creative,"1","Acme leaderboard",17,4,4', $lines);
        $this->assertContains('slot,"index_above_list","index_above_list",14,3,3', $lines);
        $this->assertContains('slot,"index_sidebar","index_sidebar",3,1,1', $lines);
    }

    /**
     * Campaign names are written by people, and a file that breaks on the
     * first comma in one is worse than no file.
     */
    #[Test]
    public function a_name_with_a_comma_or_a_quote_in_it_survives(): void
    {
        $this->database()->table('placement_campaigns')->where('id', 1)->update(['name' => 'Acme, "Inc"']);
        $this->seedBuckets();

        $expected = 'campaign,"1","Acme, ""Inc""",17,4,4';

        $this->assertContains($expected, $this->csv());
    }

    #[Test]
    public function an_empty_window_reports_nothing_rather_than_failing(): void
    {
        $body = $this->report();

        $this->assertSame([], $body['daily']);
        $this->assertSame(0, $body['totals']['impressions']);
    }
}
