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
 * `filter[type]=raw_html`, which is how somebody auditing what runs on their
 * forum finds the creatives that execute script.
 *
 * Not validated against the registry on purpose. A type belonging to an
 * extension that has since been disabled still has rows in the table, and
 * being able to list them is exactly when it matters most.
 *
 * @implements FilterInterface<DatabaseSearchState>
 */
class TypeFilter implements FilterInterface
{
    public function getFilterKey(): string
    {
        return 'type';
    }

    public function filter(SearchState $state, string|array $value, bool $negate): void
    {
        $state->getQuery()->whereIn('type', (array) $value, 'and', $negate);
    }
}
