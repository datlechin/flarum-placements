<?php

/*
 * This file is part of datlechin/flarum-placements.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\Placements\Tests\unit;

use Datlechin\Placements\Placement;
use Datlechin\Placements\PlacementRegistry;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class PlacementRegistryTest extends TestCase
{
    #[Test]
    public function it_registers_what_it_is_constructed_with(): void
    {
        $registry = new PlacementRegistry([
            new Placement(key: 'notice'),
            new Placement(key: 'header'),
        ]);

        $this->assertSame(2, $registry->count());
        $this->assertSame(['notice', 'header'], $registry->keys());
        $this->assertTrue($registry->has('notice'));
        $this->assertFalse($registry->has('nothing'));
    }

    #[Test]
    public function it_refuses_a_second_claim_on_the_same_key(): void
    {
        // Silently letting the later registration win would mean one
        // extension's slot quietly replacing another's, with no error and no
        // way to notice except that an ad stopped appearing.
        $registry = new PlacementRegistry([new Placement(key: 'notice')]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/already registered/');

        $registry->add(new Placement(key: 'notice'));
    }

    #[Test]
    public function the_duplicate_error_says_how_to_fix_it(): void
    {
        $registry = new PlacementRegistry([new Placement(key: 'header')]);

        try {
            $registry->add(new Placement(key: 'header'));
            $this->fail('Expected a duplicate key to be refused.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('acme.header', $e->getMessage());
        }
    }

    #[Test]
    public function it_says_so_plainly_when_handed_something_that_is_not_a_placement(): void
    {
        // The likely third-party mistake, because every other extender in this
        // extension takes a class string.
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/value objects rather than class strings/');

        new PlacementRegistry([Placement::class]);
    }

    #[Test]
    public function an_unknown_key_reads_back_as_null(): void
    {
        $this->assertNull((new PlacementRegistry())->get('nothing'));
    }

    #[Test]
    public function getting_an_unknown_key_or_failing_lists_what_does_exist(): void
    {
        // "Placement [sidebar] is not registered" on its own sends people
        // reading source. Naming the alternatives usually ends the search.
        $registry = new PlacementRegistry([
            new Placement(key: 'notice'),
            new Placement(key: 'index_sidebar'),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/notice, index_sidebar/');

        $registry->getOrFail('sidebar');
    }

    #[Test]
    public function it_groups_placements_the_way_the_admin_list_renders_them(): void
    {
        $registry = new PlacementRegistry([
            new Placement(key: 'notice', group: Placement::GROUP_GLOBAL),
            new Placement(key: 'index_sidebar', group: Placement::GROUP_INDEX),
            new Placement(key: 'index_above_list', group: Placement::GROUP_INDEX),
        ]);

        $grouped = $registry->grouped();

        $this->assertSame(['global', 'index'], array_keys($grouped));
        $this->assertCount(1, $grouped['global']);
        $this->assertCount(2, $grouped['index']);
    }

    #[Test]
    public function it_filters_to_the_placements_a_creative_type_may_run_in(): void
    {
        $registry = new PlacementRegistry([
            new Placement(key: 'notice'),
            new Placement(key: 'header', allowedTypes: ['text']),
        ]);

        $this->assertSame(['notice', 'header'], array_keys($registry->accepting('text')));
        $this->assertSame(['notice'], array_keys($registry->accepting('network')));
    }

    #[Test]
    public function it_serialises_as_a_list_rather_than_a_map(): void
    {
        // json_encode turns a string-keyed array into an object; the client
        // iterates this, so it has to stay an array.
        $registry = new PlacementRegistry([
            new Placement(key: 'notice'),
            new Placement(key: 'header'),
        ]);

        $serialised = $registry->toArray();

        $this->assertSame([0, 1], array_keys($serialised));
        $this->assertSame('notice', $serialised[0]['key']);
    }
}
