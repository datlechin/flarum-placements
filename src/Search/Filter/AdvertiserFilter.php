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
 * `filter[advertiser]=3`, which is every campaign one advertiser is running.
 *
 * This is what makes an advertiser row worth clicking: without it, answering
 * "what is Acme running at the moment" means reading the whole campaign list
 * and checking each row's advertiser by eye.
 *
 * @implements FilterInterface<DatabaseSearchState>
 */
class AdvertiserFilter implements FilterInterface
{
    public function getFilterKey(): string
    {
        return 'advertiser';
    }

    public function filter(SearchState $state, string|array $value, bool $negate): void
    {
        $ids = array_map(intval(...), (array) $value);

        $state->getQuery()->whereIn('advertiser_id', $ids, 'and', $negate);
    }
}
