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
use Flarum\Database\AbstractModel;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $campaign_id
 * @property string $name
 * @property string $type
 * @property string $status
 * @property int $weight
 * @property string|null $destination_url
 * @property string|null $label_override
 * @property string|null $variant_group
 * @property array<string, mixed> $payload
 * @property string|null $review_reason
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property int $impressions
 * @property int $viewable_impressions
 * @property int $clicks
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Campaign $campaign
 * @property-read Collection<int, Assignment> $assignments
 * @property-read User|null $reviewer
 */
class Creative extends AbstractModel
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    public const MIN_WEIGHT = 1;
    public const MAX_WEIGHT = 100;

    /**
     * The only schemes a first-party creative may point at. Everything else,
     * `javascript:` and `data:` in particular, turns a link into script
     * execution.
     */
    public const ALLOWED_SCHEMES = ['http', 'https'];

    protected $table = 'placement_creatives';

    public $timestamps = true;

    protected $casts = [
        'weight' => 'int',
        'payload' => 'array',
        'reviewed_at' => 'datetime',
        'impressions' => 'int',
        'viewable_impressions' => 'int',
        'clicks' => 'int',
    ];

    protected static function booted(): void
    {
        static::deleting(function (self $creative) {
            $creative->assignments()->delete();

            // A slot pointing at a creative that no longer exists would fall
            // back to nothing at all, silently.
            PlacementSetting::query()
                ->where('passback_creative_id', $creative->id)
                ->update(['passback_creative_id' => null]);
        });
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Send an approved creative back for review.
     *
     * Called when its content changes. Skipping this is how an approved
     * placeholder becomes a live advert nobody looked at.
     */
    public function requireReviewAgain(): void
    {
        if ($this->status === self::STATUS_APPROVED) {
            $this->status = self::STATUS_PENDING;
            $this->reviewed_by = null;
            $this->reviewed_at = null;
        }
    }

    /**
     * The weight this creative carries in a given slot, which its assignment
     * may override.
     */
    public function weightIn(string $placementKey): int
    {
        foreach ($this->assignments as $assignment) {
            if ($assignment->placement_key === $placementKey && $assignment->weight !== null) {
                return $assignment->weight;
            }
        }

        return $this->weight;
    }

    /**
     * Whether a destination is safe to store and to link to.
     */
    public static function isAllowedDestination(string $url): bool
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);

        return is_string($scheme) && in_array(strtolower($scheme), self::ALLOWED_SCHEMES, true);
    }
}
