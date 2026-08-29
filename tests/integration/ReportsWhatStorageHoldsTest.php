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
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * What the settings screen shows next to "Keep statistics for".
 *
 * The field on its own is a number an administrator has no way to judge: it
 * does not say how much is stored, how far back the history goes, or whether
 * lowering it would throw away something they still wanted. Worse, the setting
 * does nothing by itself -- the scheduled prune is what acts on it -- so on a
 * forum with no scheduler the field reads "90" while every bucket ever
 * recorded is still there.
 */
class ReportsWhatStorageHoldsTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('datlechin-placements');

        $this->prepareDatabase([
            'users' => [$this->normalUser()],
            'group_permission' => [],
        ]);
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

    /**
     * @return array<string, mixed>
     */
    private function storage(int $as = 1): array
    {
        $response = $this->send($this->request('GET', '/api/placements/storage', ['authenticatedAs' => $as]));

        $this->assertSame(200, $response->getStatusCode());

        return json_decode((string) $response->getBody(), true);
    }

    private function setRetention(int $days): void
    {
        $this->app()->getContainer()->make('flarum.settings')->set(Settings::RETENTION_DAYS, $days);
    }

    #[Test]
    public function an_empty_forum_reports_nothing_stored_rather_than_failing(): void
    {
        $storage = $this->storage();

        $this->assertSame(0, $storage['buckets']);
        $this->assertSame(0, $storage['expiring']);
        $this->assertNull($storage['oldest']);
    }

    #[Test]
    public function it_counts_what_is_stored(): void
    {
        $this->bucketAgedDays(1, 1);
        $this->bucketAgedDays(2, 10);

        $this->assertSame(2, $this->storage()['buckets']);
    }

    /**
     * The number that makes the setting mean something: how much of what is
     * stored the next prune would remove.
     */
    #[Test]
    public function it_says_how_much_the_retention_setting_would_delete(): void
    {
        $this->setRetention(30);

        $this->bucketAgedDays(1, 10);
        $this->bucketAgedDays(2, 60);
        $this->bucketAgedDays(3, 90);

        $storage = $this->storage();

        $this->assertSame(3, $storage['buckets']);
        $this->assertSame(2, $storage['expiring'], 'the 60- and 90-day-old buckets are past a 30-day window');
        $this->assertSame(30, $storage['retentionDays']);
    }

    /**
     * Lowering the setting has to change the answer, or the figure is not
     * reporting the setting at all.
     */
    #[Test]
    public function the_figure_follows_the_setting(): void
    {
        $this->bucketAgedDays(1, 45);

        $this->setRetention(90);
        $this->assertSame(0, $this->storage()['expiring']);

        $this->setRetention(30);
        $this->assertSame(1, $this->storage()['expiring']);
    }

    #[Test]
    public function it_says_how_far_back_the_history_goes(): void
    {
        $this->bucketAgedDays(1, 5);
        $this->bucketAgedDays(2, 200);

        $oldest = $this->storage()['oldest'];

        $this->assertNotNull($oldest);
        $this->assertSame(
            Carbon::now()->utc()->startOfHour()->subDays(200)->toDateString(),
            Carbon::parse($oldest)->toDateString()
        );
    }

    #[Test]
    public function reading_it_deletes_nothing(): void
    {
        $this->setRetention(30);
        $this->bucketAgedDays(1, 90);

        $this->storage();

        $this->assertSame(1, $this->database()->table('placement_stats')->count());
    }

    #[Test]
    public function an_ordinary_member_cannot_read_it(): void
    {
        $response = $this->send($this->request('GET', '/api/placements/storage', ['authenticatedAs' => 2]));

        $this->assertSame(403, $response->getStatusCode());
    }

    #[Test]
    public function a_guest_cannot_read_it(): void
    {
        $response = $this->send($this->request('GET', '/api/placements/storage'));

        $this->assertSame(403, $response->getStatusCode());
    }
}
