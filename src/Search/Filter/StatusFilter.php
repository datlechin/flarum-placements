<?php

/*
 * This file is part of datlechin/flarum-placements.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\Placements\Search\Filter;

use Flarum\Search\Database\DatabaseSearchState;
use Flarum\Search\Filter\FilterInterface;
use Flarum\Search\SearchState;

/**
 * `filter[status]=pending`, which is the review queue.
 *
 * Several may be asked for at once, so that a queue can show what is waiting
 * alongside what was turned down and not yet resubmitted.
 *
 * @implements FilterInterface<DatabaseSearchState>
 */
class StatusFilter implements FilterInterface
{
    public function getFilterKey(): string
    {
        return 'status';
    }

    public function filter(SearchState $state, string|array $value, bool $negate): void
    {
        // Values are bound, and a status nobody uses simply matches no rows,
        // so there is nothing to validate against a list here. Checking would
        // only turn a request that already answers correctly into a 400.
        $state->getQuery()->whereIn('status', (array) $value, 'and', $negate);
    }
}
