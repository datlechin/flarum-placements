<?php

/*
 * This file is part of datlechin/flarum-placements.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\Placements\Model;

use Datlechin\Placements\Placement;
use Flarum\Database\AbstractModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An administrator's overrides for one code-declared placement.
 *
 * A slot with no row here is not unconfigured, it is default-configured. Rows
 * appear only when somebody changes something.
 *
 * @property string $key
 * @property bool $enabled
 * @property int $max_fill
 * @property string $fallback
 * @property int|null $passback_creative_id
 * @property string $label_mode
 * @property string $rotation
 * @property int|null $reserve_phone
 * @property int|null $reserve_tablet
 * @property int|null $reserve_desktop
 * @property int|null $every_n
 * @property int|null $repeat_limit
 * @property int $sort_order
 */
class PlacementSetting extends AbstractModel
{
    public const FALLBACK_NEXT_TIER = 'next_tier';
    public const FALLBACK_HOUSE = 'house';
    public const FALLBACK_PASSBACK = 'passback';
    public const FALLBACK_COLLAPSE = 'collapse';

    public const LABEL_INHERIT = 'inherit';
    public const LABEL_ALWAYS = 'always';
    public const LABEL_NEVER = 'never';

    public const ROTATION_RANDOM = 'random';
    public const ROTATION_STICKY = 'sticky';

    protected $table = 'placement_settings';

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $casts = [
        'enabled' => 'bool',
        'max_fill' => 'int',
        'reserve_phone' => 'int',
        'reserve_tablet' => 'int',
        'reserve_desktop' => 'int',
        'every_n' => 'int',
        'repeat_limit' => 'int',
        'sort_order' => 'int',
    ];

    public function passback(): BelongsTo
    {
        return $this->belongsTo(Creative::class, 'passback_creative_id');
    }

    /**
     * Merge these overrides onto what the placement's code declares, producing
     * the effective configuration the serving path and the client both use.
     *
     * @return array{
     *     enabled: bool,
     *     maxFill: int,
     *     fallback: string,
     *     passbackCreativeId: int|null,
     *     labelMode: string,
     *     rotation: string,
     *     reserve: array<string, int>,
     *     everyN: int|null,
     *     repeatLimit: int|null,
     *     sortOrder: int
     * }
     */
    public static function resolve(Placement $placement, ?self $overrides): array
    {
        $reserve = $placement->reservedHeights();

        foreach (['phone', 'tablet', 'desktop'] as $breakpoint) {
            $override = $overrides?->{"reserve_$breakpoint"};

            if ($override !== null) {
                $reserve[$breakpoint] = $override;
            }
        }

        return [
            'enabled' => $overrides->enabled ?? true,
            'maxFill' => $overrides->max_fill ?? $placement->maxFill,
            'fallback' => $overrides->fallback ?? self::FALLBACK_HOUSE,
            'passbackCreativeId' => $overrides->passback_creative_id ?? null,
            'labelMode' => $overrides->label_mode ?? self::LABEL_INHERIT,
            'rotation' => $overrides->rotation ?? self::ROTATION_RANDOM,
            'reserve' => $reserve,

            // Only meaningful on a repeating placement; offering an "every N"
            // setting on a slot that appears once is how you get a setting
            // that visibly does nothing.
            'everyN' => $placement->repeating ? ($overrides->every_n ?? null) : null,
            'repeatLimit' => $placement->repeating ? ($overrides->repeat_limit ?? null) : null,

            'sortOrder' => $overrides->sort_order ?? 0,
        ];
    }
}
