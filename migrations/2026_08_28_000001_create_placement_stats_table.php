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
 * Delivery, counted by the hour and by nothing finer.
 *
 * A row here reads "in this hour, this creative was shown in this slot on
 * desktops 412 times". It identifies nobody, which is the point: the default
 * configuration of this extension collects no personal data at all, so a forum
 * owner does not silently become a data controller for advertising data they
 * never asked to gather.
 *
 * It is also the only affordable shape. At 1.5 million impressions a month, a
 * row per event with the indexes reporting needs is roughly three gigabytes a
 * year, on installs that are frequently on a one gigabyte shared database.
 * Hourly buckets are well under two hundred thousand rows and about thirty
 * megabytes.
 *
 * `filtered` exists so that traffic rejected as invalid is visible rather than
 * vanishing. An advertiser asking why their impressions dropped by a fifth
 * deserves an answer.
 */
return Migration::createTable('placement_stats', function (Blueprint $table) {
    $table->bigIncrements('id');

    // Truncated to the hour, in UTC. Flarum locks the server to UTC
    // unconditionally, so there is no other honest choice, and a report that
    // wants local hours converts on the way out.
    $table->dateTime('bucket_start');

    $table->integer('campaign_id')->unsigned();
    $table->integer('creative_id')->unsigned();
    $table->string('placement_key', 80);

    // Empty rather than null when unknown, so the unique index below can
    // include it: MySQL treats nulls as distinct and would happily create a
    // second row for the same bucket.
    $table->string('device', 10)->default('');

    $table->integer('impressions')->unsigned()->default(0);

    // Counted separately, never billed on. It is what turns "impressions" from
    // a vanity number into something defensible in a sponsorship conversation,
    // and it is what exposes a slot that is never actually seen.
    $table->integer('viewable_impressions')->unsigned()->default(0);

    $table->integer('clicks')->unsigned()->default(0);
    $table->integer('filtered')->unsigned()->default(0);

    $table->unique(['bucket_start', 'campaign_id', 'creative_id', 'placement_key', 'device'], 'placement_stats_bucket');
    $table->index(['campaign_id', 'bucket_start'], 'placement_stats_campaign');
    $table->index(['creative_id', 'bucket_start'], 'placement_stats_creative');
});
