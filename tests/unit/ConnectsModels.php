<?php

/*
 * This file is part of datlechin/flarum-placement.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\Placement\Tests\unit;

use Flarum\Database\AbstractModel;
use Illuminate\Database\Capsule\Manager;

/**
 * Gives Eloquent somewhere to ask about SQL grammar, without a database.
 *
 * Casting a `datetime` attribute makes Eloquent read the date format off the
 * connection's query grammar, so a model with dates cannot be constructed at
 * all until a connection resolver exists — even in a test that never reads or
 * writes a row. An in-memory SQLite connection is the cheapest way to satisfy
 * that, and it keeps the models under test exactly as they run in production
 * rather than having them carry a hardcoded `$dateFormat` for the tests' sake.
 *
 * No schema is created. A test that needs tables belongs in the integration
 * suite, which boots a real Flarum.
 */
trait ConnectsModels
{
    private static ?Manager $capsule = null;

    protected function connectModels(): void
    {
        if (self::$capsule === null) {
            self::$capsule = new Manager();
            self::$capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        }

        AbstractModel::setConnectionResolver(self::$capsule->getDatabaseManager());
    }
}
