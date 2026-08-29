<?php

/*
 * This file is part of datlechin/flarum-placements.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\Placements\Search;

use Datlechin\Placements\Model\Campaign;
use Illuminate\Database\Eloquent\Builder;

/**
 * Lets the campaign list be searched, filtered and sorted.
 *
 * @see AbstractManagedSearcher for why the permission is checked again here.
 */
class CampaignSearcher extends AbstractManagedSearcher
{
    protected function baseQuery(): Builder
    {
        return Campaign::query()->select('placement_campaigns.*');
    }
}
