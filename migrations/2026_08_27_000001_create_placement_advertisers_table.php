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
 * The party a campaign is reported to.
 *
 * Optional throughout: a forum running only its own house ads never opens this
 * screen. It exists mainly to own the report token, which is what lets an
 * advertiser check their delivery without an account on the forum.
 *
 * No database-level foreign keys anywhere in this extension, matching Flarum:
 * there is not one `->foreign()` in core or in any bundled extension, and
 * avoiding them keeps MySQL, MariaDB, PostgreSQL and SQLite behaving alike.
 * Cascades are handled by the models.
 */
return Migration::createTable('placement_advertisers', function (Blueprint $table) {
    $table->increments('id');
    $table->string('name', 150);
    $table->string('contact_email', 150)->nullable();

    // Set to null rather than deleted when the member leaves, so historical
    // reporting does not lose the advertiser.
    $table->integer('user_id')->unsigned()->nullable();

    // base64url of 32 random bytes. Unguessable, revocable, and the page it
    // opens is noindex and rate-limited.
    $table->string('report_token', 43)->nullable()->unique();
    $table->dateTime('report_token_expires_at')->nullable();

    $table->text('notes')->nullable();
    $table->dateTime('created_at')->nullable();
    $table->dateTime('updated_at')->nullable();
});
