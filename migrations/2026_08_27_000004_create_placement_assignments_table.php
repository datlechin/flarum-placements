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
 * Which creative runs in which slot.
 *
 * `placement_key` deliberately points at nothing. Placements are declared in
 * code and there is no table of them to reference — an assignment to a key
 * that no longer exists is inert rather than broken, which is the behaviour we
 * want when an extension that owned a placement is disabled.
 */
return Migration::createTable('placement_assignments', function (Blueprint $table) {
    $table->increments('id');
    $table->integer('creative_id')->unsigned();
    $table->string('placement_key', 80);

    // Overrides the creative's own weight in this slot only, for the case
    // where one creative should dominate the sidebar but not the header.
    $table->unsignedSmallInteger('weight')->nullable();

    $table->boolean('enabled')->default(true);

    $table->unique(['creative_id', 'placement_key'], 'placement_assignments_unique');
    $table->index(['placement_key', 'enabled'], 'placement_assignments_lookup');
});
