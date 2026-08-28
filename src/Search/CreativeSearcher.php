<?php

/*
 * This file is part of datlechin/flarum-placement.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\Placement\Search;

use Datlechin\Placement\Model\Creative;
use Flarum\Search\Database\AbstractSearcher;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Lets creatives be listed with a filter on them.
 *
 * This exists only because `AbstractDatabaseResource::filters()` is final and
 * throws: Flarum routes every list filter through a searcher, so a review queue
 * asking for the pending ones needs one even though nothing here searches.
 *
 * No visibility scope on the query. Creatives have no per-actor visibility of
 * their own -- who may list them is decided by the endpoint, which asserts the
 * manage permission before this runs.
 */
class CreativeSearcher extends AbstractSearcher
{
    public function getQuery(User $actor): Builder
    {
        return Creative::query()->select('placement_creatives.*');
    }
}
