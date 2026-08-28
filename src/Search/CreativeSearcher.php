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

use Datlechin\Placements\Model\Creative;
use Datlechin\Placements\Support\Permissions;
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
 * The permission is checked here as well as by the endpoint, and that is not
 * belt and braces. Flarum's Index endpoint routes through the searcher
 * *instead of* the resource's own query, so a searcher hands rows to whatever
 * asks for a list of its model -- the resource's `scope()` is never consulted.
 * A second resource over these rows nearly shipped every advertiser's
 * submissions to every member because of it, which is why the member side has
 * its own model and this has its own check.
 *
 * @see \Datlechin\Placements\Model\Submission
 */
class CreativeSearcher extends AbstractSearcher
{
    public function getQuery(User $actor): Builder
    {
        $query = Creative::query()->select('placement_creatives.*');

        if (! $actor->hasPermission(Permissions::MANAGE)) {
            // Rather than returning nothing at all, which would read as an
            // empty queue instead of as a refusal. The endpoint has already
            // answered 403 by the time anything gets here.
            $query->whereRaw('1 = 0');
        }

        return $query;
    }
}
