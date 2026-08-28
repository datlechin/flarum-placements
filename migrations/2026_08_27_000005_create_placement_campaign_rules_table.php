<?php

/*
 * This file is part of datlechin/flarum-placement.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

/**
 * Targeting, one row per include or exclude value.
 *
 * Selection reads from a cached in-memory plan either way, so normalising this
 * is not a query-performance decision. It is a correctness one: rows give
 * referential cleanup when a tag or a group is deleted, and they let "which
 * campaigns target tag 7?" be one indexed query, which both the admin UI and
 * the tag-deletion hook need to ask.
 *
 * Semantics: AND across dimensions, OR within a dimension, and an `is_not`
 * always beats an `is`. A dimension with no rows does not constrain the
 * campaign.
 */
return Migration::createTable('placement_campaign_rules', function (Blueprint $table) {
    $table->increments('id');
    $table->integer('campaign_id')->unsigned();

    // Matches a registered DimensionInterface::key().
    $table->string('dimension', 40);

    // is | is_not | gte | lte. The last two are single-valued.
    $table->string('operator', 10);

    // 191 rather than 255: this is indexed, and 191 is the longest utf8mb4
    // string that fits in the old MySQL 767-byte index limit.
    $table->string('value', 191);

    $table->index('campaign_id', 'placement_rules_campaign');
    $table->index(['dimension', 'value'], 'placement_rules_lookup');
});
