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
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One hour of delivery for one creative in one slot on one class of device.
 *
 * @property int $id
 * @property Carbon $bucket_start
 * @property int $campaign_id
 * @property int $creative_id
 * @property string $placement_key
 * @property string $device
 * @property int $impressions
 * @property int $viewable_impressions
 * @property int $clicks
 * @property int $filtered
 * @property-read Campaign|null $campaign
 * @property-read Creative|null $creative
 */
class Stat extends AbstractModel
{
    public const IMPRESSION = 'impression';
    public const VIEWABLE = 'viewable';
    public const CLICK = 'click';
    public const FILTERED = 'filtered';

    /**
     * The counter each kind of event moves.
     */
    public const COLUMNS = [
        self::IMPRESSION => 'impressions',
        self::VIEWABLE => 'viewable_impressions',
        self::CLICK => 'clicks',
        self::FILTERED => 'filtered',
    ];

    protected $table = 'placement_stats';

    protected $casts = [
        'bucket_start' => 'datetime',
        'impressions' => 'int',
        'viewable_impressions' => 'int',
        'clicks' => 'int',
        'filtered' => 'int',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function creative(): BelongsTo
    {
        return $this->belongsTo(Creative::class);
    }

    /**
     * The bucket a moment belongs to.
     *
     * UTC, because Flarum locks the server to it unconditionally and pretending
     * otherwise would produce buckets that drift twice a year.
     */
    public static function bucketFor(?Carbon $moment = null): Carbon
    {
        return ($moment ?? Carbon::now())->copy()->utc()->startOfHour();
    }

    public static function isKnownType(string $type): bool
    {
        return isset(self::COLUMNS[$type]);
    }
}
