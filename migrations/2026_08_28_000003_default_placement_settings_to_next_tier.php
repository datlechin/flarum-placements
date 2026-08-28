<?php

/*
 * This file is part of datlechin/flarum-placements.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Schema\Builder;

/**
 * `fallback` shipped defaulting to `house`, from a table created when the
 * setting did nothing: the client took the best tier and never read it, so all
 * four modes behaved as `next_tier`.
 *
 * Now that the modes are real, `house` as a default would mean every slot on
 * every forum skips the paid tiers below its best one and drops straight to
 * the forum's own adverts -- which is a behaviour change nobody asked for and
 * which costs a forum owner money.
 *
 * Written as data rather than as a column alter: changing a default across
 * MySQL, MariaDB, PostgreSQL and SQLite is four different statements, and the
 * value only matters when a row is created without naming it. Rows already
 * saying `house` are left alone -- if somebody chose it, they chose it.
 */
return [
    'up' => function (Builder $schema): void {
        $connection = $schema->getConnection();

        // Only the rows that have never been touched: `house` here means the
        // column default rather than a decision, because no interface has ever
        // offered the choice.
        $connection->table('placement_settings')
            ->where('fallback', 'house')
            ->update(['fallback' => 'next_tier']);
    },

    'down' => function (Builder $schema): void {
        $schema->getConnection()->table('placement_settings')
            ->where('fallback', 'next_tier')
            ->update(['fallback' => 'house']);
    },
];
