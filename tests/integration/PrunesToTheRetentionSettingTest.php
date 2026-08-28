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
use Datlechin\Placements\Support\Settings;
use Flarum\Testing\integration\ConsoleTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * The admin panel has a "Keep statistics for" field. It has to be the number
 * the scheduled prune actually uses.
 *
 * It was not: the command declared a default of 90 on its `--days` option, the
 * scheduler passes no arguments, and an option with a default is never absent
 * -- so the setting was read by nothing. Setting 365 lost a year's detail at
 * 90 days, which is the direction that destroys data rather than merely
 * keeping too much.
 */
class PrunesToTheRetentionSettingTest extends ConsoleTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('datlechin-placements');

        $this->prepareDatabase(['group_permission' => []]);
    }

    private function bucketAgedDays(int $id, int $days): void
    {
        $this->database()->table('placement_stats')->insert([
            'id' => $id,
            'bucket_start' => Carbon::now()->utc()->startOfHour()->subDays($days)->format('Y-m-d H:i:s'),
            'campaign_id' => 1,
            'creative_id' => 1,
            'placement_key' => 'index_above_list',
            'device' => 'desktop',
            'impressions' => 1,
            'viewable_impressions' => 0,
            'clicks' => 0,
            'filtered' => 0,
        ]);
    }

    private function remaining(): int
    {
        return $this->database()->table('placement_stats')->count();
    }

    private function setRetention(int $days): void
    {
        $this->app()->getContainer()->make('flarum.settings')->set(Settings::RETENTION_DAYS, $days);
    }

    #[Test]
    public function a_shorter_retention_setting_prunes_more(): void
    {
        $this->setRetention(30);
        $this->bucketAgedDays(1, 10);
        $this->bucketAgedDays(2, 60);

        $this->runCommand(['command' => 'placements:prune']);

        $this->assertSame(1, $this->remaining(), 'the 60-day-old bucket should have gone at a 30-day setting');
    }

    /**
     * The direction that used to lose data: a forum asking to keep a year got
     * ninety days.
     */
    #[Test]
    public function a_longer_retention_setting_keeps_more(): void
    {
        $this->setRetention(365);
        $this->bucketAgedDays(1, 200);

        $this->runCommand(['command' => 'placements:prune']);

        $this->assertSame(1, $this->remaining(), 'a 200-day-old bucket should survive a 365-day setting');
    }

    #[Test]
    public function an_explicit_option_still_overrides_the_setting(): void
    {
        $this->setRetention(365);
        $this->bucketAgedDays(1, 200);

        $this->runCommand(['command' => 'placements:prune', '--days' => '30']);

        $this->assertSame(0, $this->remaining());
    }

    #[Test]
    public function an_unset_setting_falls_back_to_the_default(): void
    {
        $this->bucketAgedDays(1, Settings::DEFAULT_RETENTION_DAYS + 10);
        $this->bucketAgedDays(2, Settings::DEFAULT_RETENTION_DAYS - 10);

        $this->runCommand(['command' => 'placements:prune']);

        $this->assertSame(1, $this->remaining());
    }
}
