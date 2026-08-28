<?php

/*
 * This file is part of datlechin/flarum-placements.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\Placements\Measurement;

use Carbon\Carbon;
use Datlechin\Placements\Model\Campaign;
use Datlechin\Placements\Model\Creative;
use Datlechin\Placements\Model\Stat;
use Datlechin\Placements\Notification\CampaignStoppedBlueprint;
use Flarum\Notification\NotificationSyncer;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Database\ConnectionInterface;

/**
 * Turns verified events into counts.
 *
 * Writes go through the cache first and reach the database in batches, because
 * a busy forum produces far more events than it can afford row-level writes
 * for, and every one of those writes would contend on the same handful of
 * rows.
 *
 * Two counters are moved for every event: the hourly bucket that reporting
 * reads, and the running total on the campaign and creative that the caps are
 * checked against. They are kept separately on purpose — a cap has to be
 * answerable without summing a year of history.
 */
class Recorder
{
    public const BUFFER_PREFIX = 'datlechin-placements.buf.';
    public const BUFFER_INDEX = 'datlechin-placements.buf.index';
    public const NONCE_PREFIX = 'datlechin-placements.nonce.';

    public function __construct(
        protected Cache $cache,
        protected ConnectionInterface $db,
        protected ?NotificationSyncer $notifications = null,
    ) {
    }

    /**
     * Whether this event has been seen before.
     *
     * `add()` writes only if the key is absent and reports whether it did, so
     * the check and the claim are one operation and two requests arriving
     * together cannot both win.
     *
     * On the `file` and `array` cache drivers that atomicity is weaker. The
     * failure mode there is a duplicate impression rather than a lost one,
     * which is the right way round.
     */
    public function claim(string $nonce, string $type): bool
    {
        return $this->cache->add(self::NONCE_PREFIX."$type.$nonce", 1, EventToken::LIFETIME);
    }

    /**
     * Buffer one event.
     *
     * @param  array{creative: int, campaign: int, placement: string, device: string}  $event
     */
    public function record(array $event, string $type, ?Carbon $now = null): void
    {
        if (! Stat::isKnownType($type)) {
            return;
        }

        $bucket = Stat::bucketFor($now)->format('Y-m-d H:00:00');
        $key = implode('|', [$bucket, $event['campaign'], $event['creative'], $event['placement'], $event['device'], $type]);

        // The index is what makes a flush possible at all: a cache has no way
        // to enumerate its own keys.
        $this->remember($key);

        $this->cache->add(self::BUFFER_PREFIX.$key, 0, self::bufferLifetime());
        $this->cache->increment(self::BUFFER_PREFIX.$key);
    }

    /**
     * Move everything buffered into the database.
     *
     * Safe to run concurrently: each key is taken out of the buffer before it
     * is written, so two flushes racing cannot double-count. A key whose value
     * is lost between the read and the delete loses those events rather than
     * duplicating them, which for advertising is the tolerable direction.
     *
     * @return int Number of buckets written.
     */
    public function flush(): int
    {
        $keys = $this->index();

        if ($keys === []) {
            return 0;
        }

        $this->cache->forget(self::BUFFER_INDEX);

        $written = 0;

        foreach ($keys as $key) {
            $buffered = $this->cache->pull(self::BUFFER_PREFIX.$key);

            // A counter that expired, or one a driver returned as something
            // other than a number, is treated as nothing rather than guessed
            // at: the alternative is inventing delivery that never happened.
            if (! is_int($buffered) && ! (is_string($buffered) && ctype_digit($buffered))) {
                continue;
            }

            $count = (int) $buffered;

            if ($count < 1) {
                continue;
            }

            [$bucket, $campaign, $creative, $placement, $device, $type] = explode('|', $key);

            $this->add(
                $bucket,
                (int) $campaign,
                (int) $creative,
                $placement,
                $device,
                Stat::COLUMNS[$type] ?? null,
                $count
            );

            $written++;
        }

        return $written;
    }

    /**
     * Add to a bucket, creating it if this is the first event of the hour.
     *
     * Increment first, insert only when nothing was updated. Laravel's
     * `upsert()` cannot be used here because it *sets* rather than adds, which
     * would throw away everything counted earlier in the hour.
     */
    protected function add(string $bucket, int $campaign, int $creative, string $placement, string $device, ?string $column, int $count): void
    {
        if ($column === null) {
            return;
        }

        $match = [
            'bucket_start' => $bucket,
            'campaign_id' => $campaign,
            'creative_id' => $creative,
            'placement_key' => $placement,
            'device' => $device,
        ];

        $updated = $this->db->table('placement_stats')->where($match)->increment($column, $count);

        if ($updated === 0) {
            try {
                $this->db->table('placement_stats')->insert($match + [$column => $count]);
            } catch (\Throwable) {
                // Another flush created the row between the update and the
                // insert. The unique index is what makes that safe to notice
                // rather than to prevent, so retry the increment.
                $this->db->table('placement_stats')->where($match)->increment($column, $count);
            }
        }

        $this->bumpRunningTotals($campaign, $creative, $column, $count);
    }

    /**
     * The counters the caps are checked against.
     *
     * Deliberately not read for reporting: reporting reads the buckets, which
     * can be pruned without a campaign suddenly becoming eligible again.
     */
    protected function bumpRunningTotals(int $campaign, int $creative, string $column, int $count): void
    {
        if ($column === 'filtered') {
            return;
        }

        if (in_array($column, ['impressions', 'clicks'], true)) {
            Campaign::query()->where('id', $campaign)->increment($column, $count);

            $this->announceIfStopped($campaign, $column);
        }

        Creative::query()->where('id', $creative)->increment($column, $count);
    }

    /**
     * Tell whoever created a campaign that it has stopped.
     *
     * A campaign that has quietly reached its cap looks exactly like one that
     * is running: the row still says "active", and the only symptom is that an
     * advertiser's numbers stop moving. Somebody finds out a week later,
     * usually the advertiser.
     *
     * Only read back when the campaign actually has a cap, which most do not.
     */
    protected function announceIfStopped(int $campaignId, string $column): void
    {
        if ($this->notifications === null) {
            return;
        }

        $cap = $column === 'impressions' ? 'max_impressions' : 'max_clicks';

        /** @var Campaign|null $campaign */
        $campaign = Campaign::query()->where('id', $campaignId)->whereNotNull($cap)->first();

        if ($campaign === null || ! $campaign->hasReachedACap()) {
            return;
        }

        $author = $campaign->author;

        if ($author === null) {
            return;
        }

        $reason = $column === 'impressions'
            ? CampaignStoppedBlueprint::IMPRESSIONS
            : CampaignStoppedBlueprint::CLICKS;

        // The syncer will not send the same blueprint for the same subject
        // twice, so crossing the line again on a later batch says nothing.
        $this->notifications->sync(new CampaignStoppedBlueprint($campaign, $reason), [$author]);
    }

    /**
     * @return list<string>
     */
    protected function index(): array
    {
        $index = $this->cache->get(self::BUFFER_INDEX);

        return is_array($index) ? array_values(array_unique(array_filter($index, 'is_string'))) : [];
    }

    protected function remember(string $key): void
    {
        $index = $this->index();

        if (in_array($key, $index, true)) {
            return;
        }

        $index[] = $key;

        $this->cache->put(self::BUFFER_INDEX, $index, self::bufferLifetime());
    }

    /**
     * Long enough that a forum with no scheduler still flushes eventually, via
     * the opportunistic path on the beacon request.
     */
    private static function bufferLifetime(): int
    {
        return 86400;
    }
}
