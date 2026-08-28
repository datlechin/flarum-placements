<?php

/*
 * This file is part of datlechin/flarum-placement.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\Placement\Tests\unit\Support;

use Datlechin\Placement\Support\Permissions;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PermissionsTest extends TestCase
{
    #[Test]
    public function ad_free_is_an_explicit_grant_and_nothing_else(): void
    {
        $this->assertTrue(Permissions::wasGranted([Permissions::VIEW_WITHOUT_ADS], Permissions::VIEW_WITHOUT_ADS));
        $this->assertFalse(Permissions::wasGranted([], Permissions::VIEW_WITHOUT_ADS));
        $this->assertFalse(Permissions::wasGranted([Permissions::MANAGE], Permissions::VIEW_WITHOUT_ADS));
    }

    #[Test]
    public function holding_every_other_permission_does_not_make_somebody_ad_free(): void
    {
        // The reason this is worth asserting: `User::hasPermission()` returns
        // true for every administrator before it looks at anything, so reaching
        // for it here would make administrators permanently ad-free and leave
        // the one person who has to check that the ads work unable to see them.
        $granted = array_values(array_diff(Permissions::all(), [Permissions::VIEW_WITHOUT_ADS]));

        $this->assertFalse(Permissions::wasGranted($granted, Permissions::VIEW_WITHOUT_ADS));
    }

    #[Test]
    public function every_permission_is_namespaced_to_this_extension(): void
    {
        // These strings are written into group_permission rows across every
        // install. Renaming one later means a migration that has to guess
        // which rows were ours.
        foreach (Permissions::all() as $permission) {
            $this->assertStringStartsWith('datlechin-placement.', $permission);
        }
    }

    #[Test]
    public function authoring_html_is_not_folded_into_managing_ads(): void
    {
        // A creative carrying raw HTML runs as same-origin JavaScript on every
        // page, including the one an administrator is looking at. It needs a
        // narrower gate than "can edit ads".
        $this->assertNotSame(Permissions::MANAGE, Permissions::AUTHOR_HTML);
        $this->assertFalse(Permissions::wasGranted([Permissions::MANAGE], Permissions::AUTHOR_HTML));
    }

    #[Test]
    public function the_permission_list_has_no_duplicates(): void
    {
        $this->assertSame(Permissions::all(), array_values(array_unique(Permissions::all())));
    }
}
