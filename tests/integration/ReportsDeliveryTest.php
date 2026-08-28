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

    #[Test]
    public function the_csv_carries_the_same_series(): void
    {
        $this->seedBuckets();

        $response = $this->send($this->request('GET', '/api/placements/report', [
            'authenticatedAs' => 1,
        ])->withQueryParams(['format' => 'csv']));

        $lines = explode("\n", trim((string) $response->getBody()));

        $this->assertSame('date,impressions,viewable,clicks', $lines[0]);
        $this->assertCount(3, $lines);
        $this->assertStringEndsWith(',10,3,3', $lines[1]);
        $this->assertStringEndsWith(',7,1,1', $lines[2]);
    }

    #[Test]
    public function an_empty_window_reports_nothing_rather_than_failing(): void
    {
        $body = $this->report();

        $this->assertSame([], $body['daily']);
        $this->assertSame(0, $body['totals']['impressions']);
    }
}
