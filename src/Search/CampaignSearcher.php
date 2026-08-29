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
 * Until this existed the admin list asked for every campaign at once and drew
 * the first fifty the server chose to send, so a forum that had sold more than
 * fifty campaigns simply could not see the rest.
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
