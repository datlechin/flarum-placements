<?php

/*
 * This file is part of datlechin/flarum-placements.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

/**
 * The unit of scheduling, targeting, caps, pacing and reporting.
 *
 * The campaign layer earns its keep by being the one place a date range, a tag
 * list and a group exclusion are written down. Without it, two variants of one
 * ad have to be kept in sync by hand, which is exactly what makes rotation and
 * A/B testing painful in the two-entity extensions.
 */
return Migration::createTable('placement_campaigns', function (Blueprint $table) {
    $table->increments('id');
    $table->integer('advertiser_id')->unsigned()->nullable();
    $table->string('name', 150);

    // An administrator's stated intent, and nothing more. Whether a campaign
    // is actually live is always computed from the dates and the caps at
    // selection time, never read from here: a large share of self-hosted
    // Flarum installs have no working `schedule:run`, so a cron-driven status
    // transition would simply never fire and campaigns would run forever.
    $table->string('status', 20)->default('draft');

    // 10 sponsorship, 30 guaranteed, 50 standard, 70 remnant, 90 house.
    // Only the highest non-empty tier is ever served, never a mixture.
    $table->unsignedTinyInteger('tier')->default(50);

    // House campaigns ignore caps and pacing and are eligible as the fallback
    // when nothing else fills.
    $table->boolean('is_house')->default(false);

    $table->dateTime('starts_at')->nullable();

    // Compared exclusively (`now < ends_at`), so a campaign ending on the 1st
    // does not serve on the 1st.
    $table->dateTime('ends_at')->nullable();

    // 168 bits, one per hour of the week, as 42 hex characters. Hex rather
    // than a binary column because fixed-length binary is not portable across
    // the four databases Flarum supports. Null means no restriction.
    $table->char('daypart_mask', 42)->nullable();

    $table->integer('max_impressions')->unsigned()->nullable();
    $table->integer('max_clicks')->unsigned()->nullable();

    // asap | even. Even delivery is a probabilistic throttle against elapsed
    // time, not a forecast: it will not hit the goal exactly, it will stop a
    // month's inventory burning in three days.
    $table->string('pacing', 10)->default('asap');

    $table->unsignedSmallInteger('frequency_cap')->nullable();
    $table->string('frequency_window', 10)->default('day');

    // Recorded so a report can state what was contracted. Nothing in this
    // extension ever charges, invoices or converts a currency.
    $table->string('rate_type', 10)->nullable();
    $table->decimal('rate_amount', 10, 2)->nullable();
    $table->char('rate_currency', 3)->nullable();
    $table->text('contract_notes')->nullable();

    // Running totals, used only to decide whether a cap has been reached.
    // Reporting reads the aggregate tables, never these.
    $table->integer('impressions')->unsigned()->default(0);
    $table->integer('clicks')->unsigned()->default(0);

    $table->integer('created_by')->unsigned()->nullable();
    $table->dateTime('created_at')->nullable();
    $table->dateTime('updated_at')->nullable();

    $table->index(['status', 'starts_at', 'ends_at'], 'placement_campaigns_live');
    $table->index('advertiser_id', 'placement_campaigns_advertiser');
});
