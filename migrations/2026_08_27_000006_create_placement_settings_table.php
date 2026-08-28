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
 * What an administrator may change about a slot that code declared.
 *
 * Rows are created lazily: a placement nobody has configured simply uses the
 * defaults on its `Placement` object. That is the same split core uses for
 * permissions, and it is why an administrator can never invent a slot that no
 * component renders.
 *
 * These are emphatically *not* Flarum settings. Writing them through the
 * settings API would dispatch `Settings\Event\Saved`, which `QueueRestarter`
 * subscribes to — so saving a banner would bounce every queue worker on the
 * forum. Adding the reserved heights as LESS config variables would be worse
 * still: it marks assets dirty, and `Assets::flushCss()` recompiles one bundle
 * per locale, so an eight-language forum would run sixteen compiles because
 * somebody nudged a slot height. The reserved height is applied as an inline
 * `min-height` on the slot instead, which costs nothing and recompiles nothing.
 */
return Migration::createTable('placement_settings', function (Blueprint $table) {
    // The code-declared placement key. No surrogate id: the key is the
    // identity, and there can only ever be one row per slot.
    $table->string('key', 80)->primary();

    $table->boolean('enabled')->default(true);
    $table->unsignedTinyInteger('max_fill')->default(1);

    // next_tier | house | passback | collapse. Roughly 30% of impressions
    // never render, so what happens when a slot cannot fill is a first-class
    // setting rather than an afterthought.
    $table->string('fallback', 12)->default('next_tier');
    $table->integer('passback_creative_id')->unsigned()->nullable();

    // inherit | always | never. The visible "Advertisement" label satisfies
    // both AdSense policy and the FTC, so it is on by default everywhere.
    $table->string('label_mode', 10)->default('inherit');

    // Overrides the heights declared on the Placement. Null means "use what
    // the code says".
    $table->unsignedSmallInteger('reserve_phone')->nullable();
    $table->unsignedSmallInteger('reserve_tablet')->nullable();
    $table->unsignedSmallInteger('reserve_desktop')->nullable();

    // Repeating placements only: show in every nth post, at most this many
    // times per page. Null repeat_limit means unlimited.
    $table->unsignedSmallInteger('every_n')->nullable();
    $table->unsignedSmallInteger('repeat_limit')->nullable();

    $table->smallInteger('sort_order')->default(0);
});
