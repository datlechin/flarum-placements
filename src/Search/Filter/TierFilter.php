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
 * `filter[tier]=10`, which is "show me what the sponsorships are doing".
 *
 * Several may be asked for at once. The values are the tier numbers rather
 * than their names, because the number is what orders them and a forum reading
 * its own API should not have to know that "guaranteed" sits above "standard".
 *
 * @implements FilterInterface<DatabaseSearchState>
 */
class TierFilter implements FilterInterface
{
    public function getFilterKey(): string
    {
        return 'tier';
    }

    public function filter(SearchState $state, string|array $value, bool $negate): void
    {
        // Cast, so that the string a query string always carries is compared
        // as the integer the column holds. PostgreSQL will not compare the two
        // and errors rather than silently matching nothing.
        $tiers = array_map(intval(...), (array) $value);

        $state->getQuery()->whereIn('tier', $tiers, 'and', $negate);
    }
}
