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
use Datlechin\Placements\Measurement\Recorder;
use Datlechin\Placements\Model\Stat;
use Flarum\Testing\integration\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * The buffer between an event and the database.
 *
 * Counters live in the cache, where an increment costs no database write. The
 * list of which counters exist cannot: a cache has no way to enumerate itself,
 * so it was one array read, modified and written back on every event -- which
 * lost data two ways, both silent. Two events arriving together each read the
 * array and the second write erased the first key; and a flush read the list
 * and then deleted it, discarding anything recorded in between.
 *
 * The list is a table now, and the tests here are mostly about the trap that
 * replaced those: a cache flag stops the list being written to on every event,
 * and a flag that outlives its row would make the next event's counter
 * invisible.
 */
class BuffersCountsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('datlechin-placements');
        $this->prepareDatabase(['group_permission' => []]);
    }

    private function recorder(): Recorder
    {
        return $this->app()->getContainer()->make(Recorder::class);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function record(Recorder $recorder, array $overrides = []): void
    {
        $recorder->record([
            'creative' => 1,
            'campaign' => 1,
            'placement' => 'index_above_list',
            'device' => 'desktop',
            ...$overrides,
        ], Stat::IMPRESSION);
    }

    private function counted(): int
    {
        return (int) $this->database()->table('placement_stats')->sum('impressions');
    }

    private function keys(): int
    {
        return $this->database()->table('placement_stat_keys')->count();
    }

    #[Test]
    public function an_event_reaches_the_database_on_a_flush(): void
    {
        $recorder = $this->recorder();

        $this->record($recorder);

        $this->assertSame(0, $this->counted(), 'nothing should be written before the flush');
        $this->assertSame(1, $recorder->flush());
        $this->assertSame(1, $this->counted());
    }

    /**
     * The trap the cache flag creates, and the reason a flush does not delete
     * the rows it consumed.
     *
     * The flag exists so the list is written to once per bucket rather than
     * once per event. If a flush removed the row while the flag lived on, the
     * next event for the same bucket would skip the insert, and its counter
     * would sit in the cache with nothing pointing at it until it expired.
     */
    #[Test]
    public function a_second_event_in_the_same_bucket_survives_an_earlier_flush(): void
    {
        $recorder = $this->recorder();

        $this->record($recorder);
        $recorder->flush();

        $this->assertSame(1, $this->counted());

        // Same creative, same slot, same hour -- so the same buffer key, and
        // the cache flag saying it has already been listed is still set.
        $this->record($recorder);
        $recorder->flush();

        $this->assertSame(2, $this->counted(), 'the second event must not be lost to the flag from the first');
    }

    #[Test]
    public function separate_buckets_are_listed_separately(): void
    {
        $recorder = $this->recorder();

        $this->record($recorder);
        $this->record($recorder, ['placement' => 'index_sidebar']);
        $this->record($recorder, ['device' => 'phone']);

        $this->assertSame(3, $this->keys());
        $this->assertSame(3, $recorder->flush());
    }

    #[Test]
    public function repeating_one_bucket_lists_it_once(): void
    {
        $recorder = $this->recorder();

        $this->record($recorder);
        $this->record($recorder);
        $this->record($recorder);

        $this->assertSame(1, $this->keys(), 'the list costs one row per bucket, not one per event');
        $this->assertSame(0, $this->counted(), 'nothing is written until the flush');

        $recorder->flush();

        $this->assertSame(3, $this->counted());
    }

    /**
     * A key names the hour it belongs to, so once that hour is far enough
     * behind, no event can ever carry it again and the row is safe to drop.
     */
    #[Test]
    public function stale_keys_are_swept_and_current_ones_are_not(): void
    {
        $recorder = $this->recorder();

        $this->record($recorder);

        $this->assertSame(1, $this->keys());
        $this->assertSame(0, $recorder->sweepKeys(), 'a key recorded a moment ago is not stale');

        $this->database()->table('placement_stat_keys')->update([
            'created_at' => Carbon::now()->subDays(10)->toDateTimeString(),
        ]);

        $this->assertSame(1, $recorder->sweepKeys());
        $this->assertSame(0, $this->keys());
    }

    #[Test]
    public function flushing_an_empty_buffer_writes_nothing(): void
    {
        $this->assertSame(0, $this->recorder()->flush());
        $this->assertSame(0, $this->counted());
    }
}
