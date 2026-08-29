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
 * `filter[campaign]=5`, which is the creatives belonging to one campaign.
 *
 * What lets a campaign have a page of its own. Before this the only way to see
 * a campaign's creatives was to fetch every creative on the forum and discard
 * the ones belonging to other campaigns in the browser.
 *
 * @implements FilterInterface<DatabaseSearchState>
 */
class CampaignFilter implements FilterInterface
{
    public function getFilterKey(): string
    {
        return 'campaign';
    }

    public function filter(SearchState $state, string|array $value, bool $negate): void
    {
        $ids = array_map(intval(...), (array) $value);

        $state->getQuery()->whereIn('campaign_id', $ids, 'and', $negate);
    }
}
