<?php

/*
 * This file is part of datlechin/flarum-placements.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\Placements\Search\Fulltext;

use Flarum\Search\AbstractFulltextFilter;
use Flarum\Search\Database\DatabaseSearchState;
use Flarum\Search\SearchState;
use Illuminate\Database\Eloquent\Builder;

/**
 * Matches what somebody typed into a search box against a name.
 *
 * `LIKE` is not one comparison. MySQL and MariaDB compare case insensitively
 * because their default collation does; PostgreSQL compares case sensitively
 * and needs `ILIKE`; SQLite folds case for ASCII and nothing else. The same
 * query written once therefore returns different rows on each, and an
 * administrator typing "acme" on PostgreSQL would be told there are no
 * advertisers by that name.
 *
 * Every driver in the test matrix is covered here rather than in each filter,
 * because getting it wrong is invisible on the database most forums run and
 * only shows up on the ones that are hardest to reproduce.
 *
 * @extends AbstractFulltextFilter<DatabaseSearchState>
 */
abstract class AbstractTextFilter extends AbstractFulltextFilter
{
    /**
     * The columns a typed fragment is matched against.
     *
     * `literal-string` rather than `string`: one of these is concatenated into
     * raw SQL below, and PHPStan is right to insist that whatever ends up
     * there was written in this repository rather than passed in.
     *
     * @return non-empty-list<literal-string>
     */
    abstract protected function columns(): array;

    public function search(SearchState $state, string $value): void
    {
        /** @var Builder<covariant \Flarum\Database\AbstractModel> $query */
        $query = $state->getQuery();
        $driver = $query->getConnection()->getDriverName();

        // Grouped, so the alternatives bind to one another rather than to
        // whatever was already on the query.
        //
        // Flarum applies the fulltext filter before every other filter, so
        // today the only condition it could reach back past is the one the
        // searcher itself adds -- and that one is the permission check. An
        // ungrouped `or` there turns `1 = 0` into `1 = 0 OR name LIKE ...`,
        // which answers an unauthorised actor with every row whose name
        // matches. The endpoint refuses first, so it is not reachable over
        // HTTP; it is still the difference between a guard that holds on its
        // own and one that holds only because something else caught it.
        $query->where(function (Builder $query) use ($driver, $value): void {
            foreach ($this->columns() as $column) {
                match ($driver) {
                    'pgsql' => $query->orWhere($column, 'ilike', '%'.$value.'%'),
                    // Lowered with `strtolower` and deliberately not
                    // `mb_strtolower`: SQLite's own `LOWER` folds ASCII only,
                    // so folding more here than the database does would stop
                    // the two sides ever matching on a non-ASCII name.
                    'sqlite' => $query->orWhereRaw('LOWER('.$column.') LIKE ?', ['%'.strtolower($value).'%']),
                    default => $query->orWhere($column, 'like', '%'.$value.'%'),
                };
            }
        });
    }
}
