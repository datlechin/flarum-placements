<?php

/*
 * This file is part of datlechin/flarum-placements.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\Placements\Tests\unit\Measurement;

use Carbon\Carbon;
use Datlechin\Placements\Measurement\Recorder;
use Datlechin\Placements\Model\Stat;
use Datlechin\Placements\Selection\PlanSource;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Database\Capsule\Manager;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The buffering half of measurement, against a real SQLite table.
 *
 * A fake connection would not exercise the part that actually matters here:
 * increment-then-insert, and what happens when two flushes meet on the same
 * bucket.
 */
class RecorderTest extends TestCase
{
    private Manager $capsule;
    private Repository $cache;
    private Recorder $recorder;

    protected function setUp(): void
    {
        $this->capsule = new Manager();
        $this->capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);

        $this->capsule->getConnection()->getSchemaBuilder()->create('placement_stats', function ($table) {
            $table->increments('id');
            $table->dateTime('bucket_start');
            $table->integer('campaign_id');
            $table->integer('creative_id');
            $table->string('placement_key');
            $table->string('device')->default('');
            $table->integer('impressions')->default(0);
            $table->integer('viewable_impressions')->default(0);
            $table->integer('clicks')->default(0);
            $table->integer('filtered')->default(0);
            $table->unique(['bucket_start', 'campaign_id', 'creative_id', 'placement_key', 'device']);
        });

        // Which counters are waiting. It is a table rather than a cache entry
        // because a cache cannot be enumerated, and the array that stood in
        // for it lost keys to concurrent writes and to the flush that deleted
        // it after reading.
        $this->capsule->getConnection()->getSchemaBuilder()->create('placement_stat_keys', function ($table) {
            $table->string('key', 191)->primary();
            $table->dateTime('created_at')->nullable();
        });

        $this->cache = new Repository(new ArrayStore());

        // The running totals live on models this test has no tables for, so
        // the recorder is given a subclass that skips them, along with the
        // plan refresh that decides from those same rows whether the cached
        // plan still holds. Both are covered by the integration suite, which
        // has a real Flarum behind it -- see StopsAtTheCapTest.
        $this->recorder = new class($this->cache, $this->capsule->getConnection(), new PlanSource($this->cache)) extends Recorder {
            protected function bumpRunningTotals(int $campaign, int $creative, string $column, int $count): void
            {
            }

            protected function refreshPlanIfDeliveryChanged(array $campaigns): void
            {
            }
        };
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function event(array $overrides = []): array
    {
        return $overrides + ['creative' => 7, 'campaign' => 3, 'placement' => 'index_above_list', 'device' => 'desktop'];
    }

    /**
     * @return list<object>
     */
    private function rows(): array
    {
        return $this->capsule->getConnection()->table('placement_stats')->get()->all();
    }

    #[Test]
    public function nothing_reaches_the_database_until_a_flush(): void
    {
        $this->recorder->record($this->event(), Stat::IMPRESSION);

        $this->assertSame([], $this->rows());
    }

    #[Test]
    public function a_flush_writes_one_row_per_bucket(): void
    {
        $this->recorder->record($this->event(), Stat::IMPRESSION);
        $this->recorder->record($this->event(), Stat::IMPRESSION);
        $this->recorder->record($this->event(), Stat::CLICK);

        $this->assertSame(2, $this->recorder->flush());

        $rows = $this->rows();

        $this->assertCount(1, $rows, 'impressions and clicks belong in the same bucket');
        $this->assertSame(2, $rows[0]->impressions);
        $this->assertSame(1, $rows[0]->clicks);
    }

    #[Test]
    public function a_second_flush_adds_to_the_bucket_rather_than_replacing_it(): void
    {
        // The reason `upsert()` cannot be used: it sets, and setting would
        // throw away everything counted earlier in the hour.
        $this->recorder->record($this->event(), Stat::IMPRESSION);
        $this->recorder->flush();

        $this->recorder->record($this->event(), Stat::IMPRESSION);
        $this->recorder->record($this->event(), Stat::IMPRESSION);
        $this->recorder->flush();

        $this->assertSame(3, $this->rows()[0]->impressions);
    }

    #[Test]
    public function flushing_twice_does_not_count_anything_twice(): void
    {
        $this->recorder->record($this->event(), Stat::IMPRESSION);

        $this->recorder->flush();
        $this->recorder->flush();

        $this->assertSame(1, $this->rows()[0]->impressions);
    }

    #[Test]
    public function an_empty_buffer_flushes_nothing(): void
    {
        $this->assertSame(0, $this->recorder->flush());
        $this->assertSame([], $this->rows());
    }

    #[Test]
    public function different_slots_and_devices_are_different_buckets(): void
    {
        $this->recorder->record($this->event(), Stat::IMPRESSION);
        $this->recorder->record($this->event(['placement' => 'discussion_sidebar']), Stat::IMPRESSION);
        $this->recorder->record($this->event(['device' => 'phone']), Stat::IMPRESSION);

        $this->recorder->flush();

        $this->assertCount(3, $this->rows());
    }

    #[Test]
    public function different_hours_are_different_buckets(): void
    {
        $this->recorder->record($this->event(), Stat::IMPRESSION, Carbon::parse('2026-08-28 10:59:59', 'UTC'));
        $this->recorder->record($this->event(), Stat::IMPRESSION, Carbon::parse('2026-08-28 11:00:00', 'UTC'));

        $this->recorder->flush();

        $rows = $this->rows();

        $this->assertCount(2, $rows);
        $this->assertSame('2026-08-28 10:00:00', $rows[0]->bucket_start);
        $this->assertSame('2026-08-28 11:00:00', $rows[1]->bucket_start);
    }

    #[Test]
    public function everything_in_one_hour_lands_in_one_bucket(): void
    {
        foreach (['10:00:00', '10:17:31', '10:59:59'] as $time) {
            $this->recorder->record($this->event(), Stat::IMPRESSION, Carbon::parse("2026-08-28 $time", 'UTC'));
        }

        $this->recorder->flush();

        $this->assertCount(1, $this->rows());
        $this->assertSame(3, $this->rows()[0]->impressions);
    }

    #[Test]
    public function an_event_type_nobody_declared_is_ignored(): void
    {
        $this->recorder->record($this->event(), 'something_invented');

        $this->assertSame(0, $this->recorder->flush());
    }

    #[Test]
    public function rejected_traffic_is_counted_rather_than_dropped(): void
    {
        // An advertiser asking why their impressions fell by a fifth deserves
        // an answer, and silently discarding the rejects means there isn't one.
        $this->recorder->record($this->event(), Stat::FILTERED);
        $this->recorder->flush();

        $this->assertSame(1, $this->rows()[0]->filtered);
        $this->assertSame(0, $this->rows()[0]->impressions);
    }

    #[Test]
    public function the_first_claim_on_an_event_wins_and_the_rest_lose(): void
    {
        // What stops one impression being reported a thousand times by
        // replaying the same request.
        $this->assertTrue($this->recorder->claim('abc123', Stat::IMPRESSION));
        $this->assertFalse($this->recorder->claim('abc123', Stat::IMPRESSION));
    }

    #[Test]
    public function an_impression_and_a_click_are_claimed_separately(): void
    {
        // They share a token, because the click has to prove the impression
        // was served — but counting one must not prevent counting the other.
        $this->assertTrue($this->recorder->claim('abc123', Stat::IMPRESSION));
        $this->assertTrue($this->recorder->claim('abc123', Stat::CLICK));
    }
}
