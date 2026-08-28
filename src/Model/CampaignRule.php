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

use Datlechin\Placement\Targeting\DimensionInterface;
use Flarum\Database\AbstractModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One include or exclude value on one targeting axis.
 *
 * @property int $id
 * @property int $campaign_id
 * @property string $dimension
 * @property string $operator
 * @property string $value
 */
class CampaignRule extends AbstractModel
{
    protected $table = 'placement_campaign_rules';

    /**
     * Whether this rule excludes rather than includes.
     *
     * An exclusion always beats an inclusion on the same axis, so a campaign
     * targeted at the "support" tag but excluded from "support.billing" does
     * not run on the latter.
     */
    public function isExclusion(): bool
    {
        return $this->operator === DimensionInterface::IS_NOT;
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}
