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
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * A reserved height describes a space that exists.
 *
 * Core lays the sidebar beside the content only at `@desktop-up`; below 992px
 * `.Page--cols()` does not apply and the sidebar is stacked above the content.
 * The extension's own stylesheet applies the tablet reserve from `@tablet-up`,
 * which is 768px -- so a tablet reserve on a sidebar slot holds a screenful of
 * blank space open above the discussion list, on a slot that is not a sidebar
 * at that width, before any advert has loaded.
 */
class SidebarReserveTest extends TestCase
{
    /**
     * @return list<Placement>
     */
    private function all(): array
    {
        return [...BuiltInPlacements::all(), ...BuiltInPlacements::tags()];
    }

    #[Test]
    public function no_sidebar_slot_reserves_height_on_a_tablet(): void
    {
        $offenders = [];

        foreach ($this->all() as $placement) {
            if (str_contains($placement->key, 'sidebar') && $placement->reserveTablet !== null) {
                $offenders[] = $placement->key;
            }
        }

        $this->assertSame([], $offenders);
    }

    /**
     * The desktop reserve is the point of the exercise and must survive the
     * fix -- a sidebar advert that loads late still pushes the column about.
     */
    #[Test]
    public function sidebar_slots_still_reserve_on_a_desktop(): void
    {
        $reserving = array_filter(
            $this->all(),
            fn (Placement $placement): bool => str_contains($placement->key, 'sidebar') && $placement->reserveDesktop !== null
        );

        $this->assertGreaterThanOrEqual(4, count($reserving));
    }

    /**
     * Nothing outside a sidebar is caught by the rule above, so a leaderboard
     * keeps the reserve it needs at every width.
     */
    #[Test]
    public function a_slot_that_is_not_a_sidebar_may_still_reserve_on_a_tablet(): void
    {
        $others = array_filter(
            $this->all(),
            fn (Placement $placement): bool => ! str_contains($placement->key, 'sidebar') && $placement->reserveTablet !== null
        );

        $this->assertNotSame([], $others, 'the rule should not have stripped every tablet reserve');
    }
}
