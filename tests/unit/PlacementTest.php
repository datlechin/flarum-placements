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
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PlacementTest extends TestCase
{
    #[Test]
    #[DataProvider('legalKeys')]
    public function it_accepts_a_legal_key(string $key): void
    {
        $this->assertSame($key, (new Placement(key: $key))->key);
    }

    public static function legalKeys(): array
    {
        return [
            'a single word' => ['notice'],
            'underscores' => ['discussion_after_op'],
            'digits after the first character' => ['slot_2'],
            'namespaced by a third party' => ['acme.profile_rail'],
            'namespaced more than once' => ['acme.widgets.rail'],
        ];
    }

    #[Test]
    #[DataProvider('illegalKeys')]
    public function it_rejects_a_key_that_is_not_safe_in_a_database_a_payload_and_a_css_class(string $key): void
    {
        // Keys reach all three, and a key that has to be escaped differently
        // in each is a key that will eventually be escaped wrongly in one.
        $this->expectException(InvalidArgumentException::class);

        new Placement(key: $key);
    }

    public static function illegalKeys(): array
    {
        return [
            'empty' => [''],
            'uppercase' => ['Notice'],
            'a hyphen' => ['post-footer'],
            'a space' => ['post footer'],
            'starting with a digit' => ['2_slot'],
            'starting with a dot' => ['.notice'],
            'trailing dot' => ['acme.'],
            'consecutive dots' => ['acme..rail'],
            'a slash, which would change the meaning of an API route' => ['acme/rail'],
            'markup' => ['<script>'],
        ];
    }

    #[Test]
    public function it_refuses_a_slot_that_can_hold_nothing(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Placement(key: 'notice', maxFill: 0);
    }

    #[Test]
    public function it_derives_translation_keys_from_the_placement_key(): void
    {
        $placement = new Placement(key: 'discussion_after_op');

        $this->assertSame('datlechin-placements.admin.placements.discussion_after_op.label', $placement->labelKey());
        $this->assertSame('datlechin-placements.admin.placements.discussion_after_op.description', $placement->descriptionKey());
    }

    #[Test]
    public function a_third_party_supplies_its_own_translation_keys(): void
    {
        // The derived default points into this extension's locale namespace,
        // which another extension cannot write to.
        $placement = new Placement(
            key: 'acme.profile_rail',
            label: 'acme-widgets.admin.placements.profile_rail.label',
            description: 'acme-widgets.admin.placements.profile_rail.description',
        );

        $this->assertSame('acme-widgets.admin.placements.profile_rail.label', $placement->labelKey());
        $this->assertSame('acme-widgets.admin.placements.profile_rail.description', $placement->descriptionKey());
    }

    #[Test]
    public function an_unrestricted_placement_accepts_every_creative_type(): void
    {
        $placement = new Placement(key: 'notice');

        $this->assertTrue($placement->accepts('image'));
        $this->assertTrue($placement->accepts('network'));
        $this->assertTrue($placement->accepts('something_a_third_party_invented'));
    }

    #[Test]
    public function a_restricted_placement_accepts_only_what_it_names(): void
    {
        $placement = new Placement(key: 'header', allowedTypes: ['text', 'image']);

        $this->assertTrue($placement->accepts('text'));
        $this->assertTrue($placement->accepts('image'));
        $this->assertFalse($placement->accepts('network'));
    }

    #[Test]
    public function it_reports_only_the_breakpoints_that_reserve_space(): void
    {
        // A breakpoint with no reservation must be absent rather than zero:
        // the stylesheet reserves space with min-height, and `min-height: 0`
        // is not the same instruction as "do not reserve".
        $placement = new Placement(key: 'index_sidebar', reserveTablet: 600, reserveDesktop: 600);

        $this->assertSame(['tablet' => 600, 'desktop' => 600], $placement->reservedHeights());
    }

    #[Test]
    public function a_placement_that_reserves_nothing_reports_nothing(): void
    {
        $this->assertSame([], (new Placement(key: 'notice'))->reservedHeights());
    }

    #[Test]
    public function it_serialises_everything_the_client_needs_to_lay_the_slot_out(): void
    {
        $placement = new Placement(
            key: 'post_footer',
            group: Placement::GROUP_DISCUSSION,
            allowedTypes: ['image'],
            maxFill: 2,
            repeating: true,
            recommendedSize: [468, 60],
            reservePhone: 50,
            reserveDesktop: 60,
        );

        $this->assertSame([
            'key' => 'post_footer',
            'group' => 'discussion',
            'label' => 'datlechin-placements.admin.placements.post_footer.label',
            'description' => 'datlechin-placements.admin.placements.post_footer.description',
            'allowedTypes' => ['image'],
            'maxFill' => 2,
            'repeating' => true,
            'recommendedSize' => [468, 60],
            'reserve' => ['phone' => 50, 'desktop' => 60],
        ], $placement->toArray());
    }
}
