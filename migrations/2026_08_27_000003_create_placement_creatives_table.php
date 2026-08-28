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
 * The thing that actually gets rendered.
 *
 * `payload` is validated and normalised by the registered creative type, never
 * free-form. Typing the creative is what makes it possible to preview it,
 * measure it, lazily load it, gate it behind consent, and give a network unit
 * the exact lifecycle its vendor requires.
 */
return Migration::createTable('placement_creatives', function (Blueprint $table) {
    $table->increments('id');
    $table->integer('campaign_id')->unsigned();
    $table->string('name', 150);

    // Matches a registered CreativeTypeInterface::key() and a frontend
    // renderer registered under the same key.
    $table->string('type', 20);

    // draft | pending | approved | rejected. Editing an approved creative
    // sends it back to pending, which is the rule everyone forgets.
    $table->string('status', 20)->default('approved');

    // Relative share within its tier, 1..100.
    $table->unsignedSmallInteger('weight')->default(10);

    // First-party types only. The scheme is checked against http/https on
    // save, which is what stops `javascript:` and `data:` creatives.
    $table->string('destination_url', 2000)->nullable();

    $table->string('label_override', 50)->nullable();

    // Two creatives sharing a variant group are treated as an A/B pair and
    // reported side by side.
    $table->string('variant_group', 50)->nullable();

    $table->json('payload');

    $table->string('review_reason', 255)->nullable();
    $table->integer('reviewed_by')->unsigned()->nullable();
    $table->dateTime('reviewed_at')->nullable();

    // Cap counters, as on campaigns. Reporting uses the aggregate tables.
    $table->integer('impressions')->unsigned()->default(0);
    $table->integer('viewable_impressions')->unsigned()->default(0);
    $table->integer('clicks')->unsigned()->default(0);

    $table->dateTime('created_at')->nullable();
    $table->dateTime('updated_at')->nullable();

    $table->index(['campaign_id', 'status'], 'placement_creatives_campaign');
});
