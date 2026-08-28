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

use Datlechin\Placements\BuiltInPlacements;
use Datlechin\Placements\Placement;
use Datlechin\Placements\PlacementRegistry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

class BuiltInPlacementsTest extends TestCase
{
    /**
     * @return list<Placement>
     */
    private static function everyPlacement(): array
    {
        return [...BuiltInPlacements::all(), ...BuiltInPlacements::tags()];
    }

    /**
     * @return array<string, mixed>
     */
    private static function locale(): array
    {
        /** @var array<string, mixed> $parsed */
        $parsed = Yaml::parseFile(__DIR__.'/../../locale/en.yml');

        return $parsed['datlechin-placements'] ?? [];
    }

    public static function placements(): array
    {
        return array_map(fn (Placement $p) => [$p], self::everyPlacement());
    }

    #[Test]
    public function the_registry_accepts_every_placement_this_extension_ships(): void
    {
        // Which is a duplicate-key check as much as anything: the registry
        // throws on a second claim, and it is easy to add a placement to two
        // groups by accident.
        $registry = new PlacementRegistry(self::everyPlacement());

        $this->assertSame(count(self::everyPlacement()), $registry->count());
    }

    #[Test]
    public function the_tag_directory_is_not_registered_unconditionally(): void
    {
        // It is only reachable when flarum/tags is enabled, so registering it
        // in all() would put a slot in the admin panel that can never render.
        $unconditional = array_map(fn (Placement $p) => $p->key, BuiltInPlacements::all());

        $this->assertNotContains('tags_page', $unconditional);
        $this->assertSame(['tags_page'], array_map(fn (Placement $p) => $p->key, BuiltInPlacements::tags()));
    }

    #[Test]
    #[DataProvider('placements')]
    public function every_placement_has_a_name_and_a_description_an_administrator_can_read(Placement $placement): void
    {
        // Without this, adding a placement and forgetting the locale entry
        // ships an admin panel row reading
        // "datlechin-placements.admin.placements.foo.label".
        $entry = self::locale()['admin']['placements'][$placement->key] ?? null;

        $this->assertIsArray($entry, "No locale entry for placement [$placement->key].");
        $this->assertNotEmpty($entry['label'] ?? '', "Placement [$placement->key] has no label.");
        $this->assertNotEmpty($entry['description'] ?? '', "Placement [$placement->key] has no description.");
    }

    #[Test]
    #[DataProvider('placements')]
    public function every_placement_uses_its_own_locale_namespace(Placement $placement): void
    {
        $this->assertStringStartsWith('datlechin-placements.', $placement->labelKey());
        $this->assertStringStartsWith('datlechin-placements.', $placement->descriptionKey());
    }

    #[Test]
    public function every_group_in_use_has_a_heading(): void
    {
        $headings = self::locale()['admin']['placement_groups'] ?? [];

        foreach (self::everyPlacement() as $placement) {
            $this->assertArrayHasKey(
                $placement->group,
                $headings,
                "Group [$placement->group], used by placement [$placement->key], has no heading."
            );
        }
    }

    #[Test]
    #[DataProvider('placements')]
    public function a_placement_reserves_space_wherever_it_recommends_a_size(Placement $placement): void
    {
        // Reserving nothing while recommending a 728x90 is how you ship the
        // layout shift the reservation exists to prevent. Desktop is the
        // breakpoint that always applies; phone and tablet may legitimately
        // collapse.
        if ($placement->recommendedSize === null) {
            $this->markTestSkipped("Placement [$placement->key] recommends no size.");
        }

        $this->assertNotNull(
            $placement->reserveDesktop,
            "Placement [$placement->key] recommends a size but reserves no space on desktop."
        );
    }

    #[Test]
    #[DataProvider('placements')]
    public function a_reserved_height_matches_the_size_it_recommends(Placement $placement): void
    {
        // If the reservation is shorter than the creative, the slot still
        // shifts when it fills; if it is taller, the page carries a permanent
        // gap. Only the desktop pair has to agree — phone and tablet serve
        // different units.
        if ($placement->recommendedSize === null || $placement->reserveDesktop === null) {
            $this->markTestSkipped("Placement [$placement->key] has no desktop pair to compare.");
        }

        $this->assertSame(
            $placement->recommendedSize[1],
            $placement->reserveDesktop,
            "Placement [$placement->key] recommends a {$placement->recommendedSize[1]}px unit but reserves {$placement->reserveDesktop}px."
        );
    }

    #[Test]
    public function no_sidebar_placement_recommends_a_unit_wider_than_the_sidebar(): void
    {
        // --sidebar-width is 190px, widening to 260px and then 280px
        // (less/forum/PageStructure.less), and DiscussionPage narrows it to
        // 180px. A 300x250 fits none of them, which is exactly the mistake
        // that is easy to make when copying an IAB size chart.
        $narrowest = 180;

        foreach (self::everyPlacement() as $placement) {
            if (! str_contains($placement->key, 'sidebar') || $placement->recommendedSize === null) {
                continue;
            }

            $this->assertLessThanOrEqual(
                $narrowest,
                $placement->recommendedSize[0],
                "Placement [$placement->key] recommends a {$placement->recommendedSize[0]}px unit, wider than the {$narrowest}px sidebar."
            );
        }
    }

    #[Test]
    public function only_slots_that_can_appear_more_than_once_are_marked_repeating(): void
    {
        $repeating = array_values(array_map(
            fn (Placement $p) => $p->key,
            array_filter(self::everyPlacement(), fn (Placement $p) => $p->repeating)
        ));

        // post_footer renders inside every nth post. Everything else appears
        // at most once per page, and marking it repeating would offer the
        // administrator an "every N" setting that does nothing.
        $this->assertSame(['post_footer'], $repeating);
    }
}
