<?php

/*
 * This file is part of datlechin/flarum-placement.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\Placement\Model;

use Carbon\Carbon;
use Datlechin\Placement\Selection\Liveness;
use Flarum\Database\AbstractModel;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int|null $advertiser_id
 * @property string $name
 * @property string $status
 * @property int $tier
 * @property bool $is_house
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property string|null $daypart_mask
 * @property int|null $max_impressions
 * @property int|null $max_clicks
 * @property string $pacing
 * @property int|null $frequency_cap
 * @property string $frequency_window
 * @property string|null $rate_type
 * @property string|null $rate_amount
 * @property string|null $rate_currency
 * @property string|null $contract_notes
 * @property int $impressions
 * @property int $clicks
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Advertiser|null $advertiser
 * @property-read Collection<int, Creative> $creatives
 * @property-read Collection<int, CampaignRule> $rules
 * @property-read User|null $author
 */
class Campaign extends AbstractModel
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_ARCHIVED = 'archived';

    public const TIER_SPONSORSHIP = 10;
    public const TIER_GUARANTEED = 30;
    public const TIER_STANDARD = 50;
    public const TIER_REMNANT = 70;
    public const TIER_HOUSE = 90;

    public const PACING_ASAP = 'asap';
    public const PACING_EVEN = 'even';

    public const WINDOW_SESSION = 'session';
    public const WINDOW_HOUR = 'hour';
    public const WINDOW_DAY = 'day';

    protected $table = 'placement_campaigns';

    public $timestamps = true;

    protected $casts = [
        'tier' => 'int',
        'is_house' => 'bool',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'max_impressions' => 'int',
        'max_clicks' => 'int',
        'frequency_cap' => 'int',
        'impressions' => 'int',
        'clicks' => 'int',
    ];

    protected static function booted(): void
    {
        static::deleting(function (self $campaign) {
            // Creatives are deleted one at a time rather than in a single
            // query, because each has assignments and passback references of
            // its own to clean up.
            $campaign->creatives->each->delete();
            $campaign->rules()->delete();
        });
    }

    public function advertiser(): BelongsTo
    {
        return $this->belongsTo(Advertiser::class);
    }

    public function creatives(): HasMany
    {
        return $this->hasMany(Creative::class);
    }

    public function rules(): HasMany
    {
        return $this->hasMany(CampaignRule::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Whether this campaign may be served right now.
     *
     * Computed, never read from `status`. A large share of self-hosted Flarum
     * installs never added `* * * * * php flarum schedule:run`, so a campaign
     * whose expiry depended on a scheduled job would run forever on exactly
     * the installs least able to notice.
     *
     * `status` still has a veto: an administrator who paused a campaign means
     * it, whatever the dates say.
     */
    public function isLive(?Carbon $now = null): bool
    {
        return Liveness::of($this->livenessAttributes(), $now);
    }

    /**
     * House campaigns exist to fill space nobody paid for, so they ignore caps
     * entirely.
     */
    public function hasReachedACap(): bool
    {
        return Liveness::capped($this->livenessAttributes());
    }

    /**
     * The serving path works from a cached array rather than from models, so
     * the rule lives in {@see Liveness} and both callers go through it.
     *
     * @return array{
     *     status: string,
     *     starts_at: Carbon|null,
     *     ends_at: Carbon|null,
     *     is_house: bool,
     *     max_impressions: int|null,
     *     max_clicks: int|null,
     *     impressions: int,
     *     clicks: int
     * }
     */
    private function livenessAttributes(): array
    {
        return [
            'status' => $this->status,
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'is_house' => $this->is_house,
            'max_impressions' => $this->max_impressions,
            'max_clicks' => $this->max_clicks,
            'impressions' => $this->impressions,
            'clicks' => $this->clicks,
        ];
    }

    /**
     * How far through its flight this campaign is, from 0 to 1, or null when
     * it has no bounded flight to be through.
     */
    public function elapsedFraction(?Carbon $now = null): ?float
    {
        if ($this->starts_at === null || $this->ends_at === null) {
            return null;
        }

        $total = $this->ends_at->getTimestamp() - $this->starts_at->getTimestamp();

        if ($total <= 0) {
            return null;
        }

        $elapsed = ($now ?? Carbon::now())->getTimestamp() - $this->starts_at->getTimestamp();

        return max(0.0, min(1.0, $elapsed / $total));
    }
}
