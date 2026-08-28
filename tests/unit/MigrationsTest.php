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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * A migration that does not return what Flarum expects is only discovered when
 * somebody installs the extension, which is the worst moment to discover it.
 */
class MigrationsTest extends TestCase
{
    private const DIR = __DIR__.'/../../migrations';

    public static function migrations(): array
    {
        $files = glob(self::DIR.'/*.php');

        return array_map(fn (string $file) => [basename($file), $file], $files ?: []);
    }

    #[Test]
    public function there_are_migrations_to_check(): void
    {
        // Guards the data provider: an empty glob would make every test below
        // pass by never running.
        $this->assertNotEmpty(self::migrations());
    }

    #[Test]
    #[DataProvider('migrations')]
    public function every_migration_returns_an_up_and_a_down(string $name, string $file): void
    {
        $migration = require $file;

        $this->assertIsArray($migration, "$name did not return an array.");
        $this->assertArrayHasKey('up', $migration, "$name has no 'up'.");
        $this->assertArrayHasKey('down', $migration, "$name has no 'down'.");
        $this->assertIsCallable($migration['up'], "$name has an 'up' that is not callable.");
        $this->assertIsCallable($migration['down'], "$name has a 'down' that is not callable.");
    }

    #[Test]
    #[DataProvider('migrations')]
    public function every_migration_is_named_the_way_flarum_orders_them(string $name, string $file): void
    {
        // Flarum sorts migrations by filename, so a file that does not start
        // with a timestamp runs in an order nobody intended.
        $this->assertMatchesRegularExpression('/^\d{4}_\d{2}_\d{2}_\d{6}_[a-z0-9_]+\.php$/', $name);
    }

    #[Test]
    public function tables_are_created_before_anything_points_at_them(): void
    {
        // Filename order is execution order, and creatives reference campaigns
        // while settings reference creatives.
        $order = array_map(fn (array $row) => $row[0], self::migrations());
        sort($order);

        $position = function (string $table) use ($order): int {
            foreach ($order as $i => $name) {
                if (str_contains($name, $table)) {
                    return $i;
                }
            }

            $this->fail("No migration creates [$table].");
        };

        $this->assertLessThan($position('creatives'), $position('campaigns'));
        $this->assertLessThan($position('assignments'), $position('creatives'));
        $this->assertLessThan($position('campaign_rules'), $position('campaigns'));
        $this->assertLessThan($position('settings'), $position('creatives'));
        $this->assertLessThan($position('campaigns'), $position('advertisers'));
    }
}
