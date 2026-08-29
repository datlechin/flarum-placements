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

use Datlechin\Placements\Support\Permissions;
use Flarum\Search\Database\AbstractSearcher;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * A searcher over rows only somebody who administers adverts may read.
 *
 * The permission is checked here as well as by the endpoint, and that is not
 * belt and braces. Flarum's Index endpoint routes through the searcher
 * *instead of* the resource's own query, so a searcher hands rows to whatever
 * asks for a list of its model and the resource's `scope()` is never consulted.
 * A second resource over the creative rows nearly shipped every advertiser's
 * submissions to every member because of exactly that.
 *
 * It lives in one place rather than in each searcher because it is the sort of
 * check that is easy to leave out of the next one, and leaving it out is silent:
 * the listing simply starts answering people it should refuse.
 *
 * @see \Datlechin\Placements\Model\Submission
 */
abstract class AbstractManagedSearcher extends AbstractSearcher
{
    /**
     * The rows this searcher lists, before the permission is considered.
     *
     * Selecting the table explicitly rather than `*`: a filter is free to join,
     * and an unqualified `*` would then hydrate the model from whichever
     * columns the join happened to add.
     */
    abstract protected function baseQuery(): Builder;

    public function getQuery(User $actor): Builder
    {
        $query = $this->baseQuery();

        if (! $actor->hasPermission(Permissions::MANAGE)) {
            // Rather than returning nothing at all, which would read as an
            // empty list instead of as a refusal. The endpoint has already
            // answered 403 by the time anything gets here.
            $query->whereRaw('1 = 0');
        }

        return $query;
    }
}
