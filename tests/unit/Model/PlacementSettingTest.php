<?php

/*
 * This file is part of datlechin/flarum-placements.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\Placements\Tests\unit\Model;

use Datlechin\Placements\Model\PlacementSetting;
use Datlechin\Placements\Placement;
use Datlechin\Placements\Tests\unit\ConnectsModels;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PlacementSettingTest extends TestCase
{
    use ConnectsModels;

    protected function setUp(): void
    {
        $this->connectModels();
    }

    private function overrides(array $attributes): PlacementSetting
    {
        $setting = new PlacementSetting();
        $setting->forceFill($attributes);

        return $setting;
    }

    #[Test]
    public function a_slot_nobody_configured_is_default_configured(): void
    {
        $placement = new Placement(key: 'notice', maxFill: 2, reserveDesktop: 90);

        $this->assertSame([
            'enabled' => true,
            'maxFill' => 2,
            'fallback' => PlacementSetting::FALLBACK_HOUSE,
            'passbackCreativeId' => null,
            'labelMode' => PlacementSetting::LABEL_INHERIT,
            'rotation' => PlacementSetting::ROTATION_RANDOM,
            'reserve' => ['desktop' => 90],
            'everyN' => null,
            'repeatLimit' => null,
            'sortOrder' => 0,
        ], PlacementSetting::resolve($placement, null));
    }

    #[Test]
    public function overrides_win_over_what_the_code_declares(): void
    {
        $placement = new Placement(key: 'notice', maxFill: 1, reserveDesktop: 90);

        $resolved = PlacementSetting::resolve($placement, $this->overrides([
            'enabled' => false,
            'max_fill' => 3,
            'fallback' => PlacementSetting::FALLBACK_COLLAPSE,
            'label_mode' => PlacementSetting::LABEL_NEVER,
            'sort_order' => 5,
        ]));

        $this->assertFalse($resolved['enabled']);
        $this->assertSame(3, $resolved['maxFill']);
        $this->assertSame(PlacementSetting::FALLBACK_COLLAPSE, $resolved['fallback']);
        $this->assertSame(PlacementSetting::LABEL_NEVER, $resolved['labelMode']);
        $this->assertSame(5, $resolved['sortOrder']);
    }

    #[Test]
    public function a_reserved_height_can_be_overridden_one_breakpoint_at_a_time(): void
    {
        $placement = new Placement(key: 'notice', reservePhone: 100, reserveTablet: 90, reserveDesktop: 90);

        $resolved = PlacementSetting::resolve($placement, $this->overrides(['reserve_desktop' => 250]));

        // The breakpoints that were not overridden keep what the code says.
        $this->assertSame(['phone' => 100, 'tablet' => 90, 'desktop' => 250], $resolved['reserve']);
    }

    #[Test]
    public function an_override_can_reserve_space_the_code_declared_none_for(): void
    {
        $placement = new Placement(key: 'notice');

        $resolved = PlacementSetting::resolve($placement, $this->overrides(['reserve_phone' => 50]));

        $this->assertSame(['phone' => 50], $resolved['reserve']);
    }

    #[Test]
    public function a_slot_that_appears_once_gets_no_repeat_settings(): void
    {
        // Even when a row in the database says otherwise. Offering an
        // "every N" control on a slot that renders once is a setting that
        // visibly does nothing, which is worse than no setting.
        $placement = new Placement(key: 'notice', repeating: false);

        $resolved = PlacementSetting::resolve($placement, $this->overrides([
            'every_n' => 5,
            'repeat_limit' => 3,
        ]));

        $this->assertNull($resolved['everyN']);
        $this->assertNull($resolved['repeatLimit']);
    }

    #[Test]
    public function a_repeating_slot_keeps_them(): void
    {
        $placement = new Placement(key: 'post_footer', repeating: true);

        $resolved = PlacementSetting::resolve($placement, $this->overrides([
            'every_n' => 5,
            'repeat_limit' => 3,
        ]));

        $this->assertSame(5, $resolved['everyN']);
        $this->assertSame(3, $resolved['repeatLimit']);
    }

    #[Test]
    public function the_key_is_the_identity_so_there_is_no_surrogate_id(): void
    {
        $setting = new PlacementSetting();

        $this->assertSame('key', $setting->getKeyName());
        $this->assertFalse($setting->getIncrementing());
        $this->assertSame('string', $setting->getKeyType());
    }

    #[Test]
    public function a_slot_draws_again_on_every_page_unless_told_otherwise(): void
    {
        // Rotating is what an administrator expects a weighted draw to do;
        // holding the choice is the deliberate exception.
        $placement = new Placement(key: 'notice');

        $this->assertSame(
            PlacementSetting::ROTATION_RANDOM,
            PlacementSetting::resolve($placement, null)['rotation']
        );

        $this->assertSame(
            PlacementSetting::ROTATION_STICKY,
            PlacementSetting::resolve($placement, $this->overrides(['rotation' => PlacementSetting::ROTATION_STICKY]))['rotation']
        );
    }
}
