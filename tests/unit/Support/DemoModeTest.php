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

use Datlechin\Placement\Support\DemoMode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DemoModeTest extends TestCase
{
    #[Test]
    #[DataProvider('switchesOn')]
    public function the_query_parameter_switches_demo_mode_on(string $param): void
    {
        $this->assertTrue(DemoMode::nextState($param, false));
    }

    public static function switchesOn(): array
    {
        return [
            'one' => ['1'],
            'true' => ['true'],
            'on' => ['on'],
            'yes' => ['yes'],
        ];
    }

    #[Test]
    #[DataProvider('switchesOff')]
    public function the_query_parameter_switches_demo_mode_off(string $param): void
    {
        $this->assertFalse(DemoMode::nextState($param, true));
    }

    public static function switchesOff(): array
    {
        return [
            'zero' => ['0'],
            'false' => ['false'],
            'off' => ['off'],
            'no' => ['no'],
        ];
    }

    #[Test]
    public function without_the_parameter_the_stored_state_carries_over(): void
    {
        // Otherwise demo mode would switch itself off the moment the
        // administrator clicked a link, which is the moment it becomes useful.
        $this->assertTrue(DemoMode::nextState(null, true));
        $this->assertFalse(DemoMode::nextState(null, false));
    }

    #[Test]
    public function an_empty_parameter_is_not_an_instruction(): void
    {
        $this->assertTrue(DemoMode::nextState('', true));
        $this->assertFalse(DemoMode::nextState('', false));
    }

    #[Test]
    #[DataProvider('nonsense')]
    public function a_value_that_means_nothing_leaves_the_state_alone(string $param): void
    {
        // Rather than reading as false, which would make a mistyped URL
        // silently switch demo mode off and look like the feature is broken.
        $this->assertTrue(DemoMode::nextState($param, true));
        $this->assertFalse(DemoMode::nextState($param, false));
    }

    public static function nonsense(): array
    {
        return [
            'a word' => ['banana'],
            'a number that is neither' => ['7'],
            'markup' => ['<script>'],
        ];
    }
}
