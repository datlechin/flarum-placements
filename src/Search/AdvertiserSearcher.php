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

use Datlechin\Placements\Model\Advertiser;
use Illuminate\Database\Eloquent\Builder;

/**
 * Lets the advertiser list be searched and sorted.
 *
 * @see AbstractManagedSearcher for why the permission is checked again here.
 */
class AdvertiserSearcher extends AbstractManagedSearcher
{
    protected function baseQuery(): Builder
    {
        return Advertiser::query()->select('placement_advertisers.*');
    }
}
