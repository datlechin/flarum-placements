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
 * The list grows on its own: a member submitting an advert has an advertiser
 * record created for them, so on a forum that accepts submissions this is the
 * one list nobody chose the length of.
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
