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
use Illuminate\Database\Eloquent\Builder;

/**
 * `filter[source]=member`, which separates what members submitted from what
 * was sold.
 *
 * A member submitting an advert has an advertiser and a campaign created for
 * them, both named after them. Those rows then sit in the campaign list
 * alongside real, paid campaigns and are indistinguishable from them without
 * knowing that mechanism exists. On a forum that accepts submissions they are
 * also the rows that arrive fastest, so they are what fills the list.
 *
 * A campaign counts as member-submitted when the advertiser it belongs to is
 * a forum account. That is the same fact `MemberInventory` writes when it
 * provisions one, so nothing extra has to be stored to ask this.
 *
 * @implements FilterInterface<DatabaseSearchState>
 */
class SourceFilter implements FilterInterface
{
    public const MEMBER = 'member';
    public const DIRECT = 'direct';

    public function getFilterKey(): string
    {
        return 'source';
    }

    public function filter(SearchState $state, string|array $value, bool $negate): void
    {
        $wanted = is_array($value) ? (string) reset($value) : $value;

        // `$negate` folded into the comparison rather than wrapping the query,
        // so that `filter[-source]=member` and `filter[source]=direct` are the
        // same question and answer identically.
        $member = ($wanted === self::MEMBER) !== $negate;

        $linked = fn (Builder $query) => $query->whereNotNull('user_id');

        // `whereDoesntHave` rather than a negated `whereHas`: a campaign with
        // no advertiser at all is direct, and a plain negation would exclude it
        // from both halves and lose it entirely.
        $member
            ? $state->getQuery()->whereHas('advertiser', $linked)
            : $state->getQuery()->whereDoesntHave('advertiser', $linked);
    }
}
