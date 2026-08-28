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

/**
 * Whether a slot draws again on every page, or keeps what it drew.
 *
 * `random` is the default because it is what an administrator expects a
 * weighted rotation to do. `sticky` exists because a sponsor whose advert
 * flickers between three others as a reader moves through the forum looks like
 * a forum with a fault, and because holding the choice for a visit makes the
 * click-through rate of one creative mean something rather than being an
 * average of whatever happened to be drawn.
 */
return Migration::addColumns('placement_settings', [
    'rotation' => ['string', 'length' => 10, 'default' => 'random'],
]);
