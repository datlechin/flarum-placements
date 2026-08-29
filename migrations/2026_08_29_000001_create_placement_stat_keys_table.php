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
 * Which buffered counters are waiting to be flushed.
 *
 * The counters themselves stay in the cache, where an increment is cheap and
 * an event costs no database write. What cannot stay there is the list of
 * which keys exist: a cache cannot be enumerated, so the list was a single
 * array read, modified and written back on every event, and that lost data two
 * ways.
 *
 * Two events arriving together each read the array, each appended their own
 * key, and the second write erased the first -- so one counter was left with
 * nothing pointing at it and expired unflushed. And `flush()` read the list and
 * then deleted it, so anything recorded in the moment between was thrown away
 * with it. Both are silent: the impressions simply never appear.
 *
 * As a table it costs one insert per bucket rather than per event -- a bucket
 * is one creative, in one slot, on one device, for one hour -- and it cannot
 * be lost, because `insertOrIgnore` does not read first and a delete names the
 * rows it consumed.
 */
return Migration::createTable('placement_stat_keys', function (Blueprint $table) {
    // The buffer key: bucket, campaign, creative, placement, device and type,
    // joined. 191 rather than 255 so the primary key fits in InnoDB's index
    // limit under utf8mb4 on older MySQL.
    $table->string('key', 191)->primary();

    // When it was first seen, so a sweep can drop a row whose counter expired
    // without ever being flushed -- which happens on a forum that stops
    // serving with a buffer part-full.
    $table->dateTime('created_at')->nullable();
});
