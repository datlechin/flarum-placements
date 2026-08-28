<?php

/*
 * This file is part of datlechin/flarum-placements.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\Placements\Tests\unit\Selection;

use Carbon\Carbon;
use Datlechin\Placements\Selection\Daypart;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DaypartTest extends TestCase
{
    /** Monday, 2026-08-24, 09:00 UTC. */
    private function mondayMorning(): Carbon
    {
        return Carbon::parse('2026-08-24T09:00:00+00:00');
    }

    #[Test]
    public function the_week_starts_on_monday_at_midnight(): void
    {
        $this->assertSame(0, Daypart::indexFor(Carbon::parse('2026-08-24T00:00:00+00:00')));
        $this->assertSame(9, Daypart::indexFor($this->mondayMorning()));
        $this->assertSame(23, Daypart::indexFor(Carbon::parse('2026-08-24T23:59:59+00:00')));
    }

    #[Test]
    public function each_day_is_the_next_twenty_four_slots(): void
    {
        $this->assertSame(24, Daypart::indexFor(Carbon::parse('2026-08-25T00:00:00+00:00')), 'Tuesday midnight');
        $this->assertSame(144, Daypart::indexFor(Carbon::parse('2026-08-30T00:00:00+00:00')), 'Sunday midnight');
        $this->assertSame(167, Daypart::indexFor(Carbon::parse('2026-08-30T23:00:00+00:00')), 'Sunday, last hour');
    }

    #[Test]
    public function the_hours_are_the_forums_rather_than_the_servers(): void
    {
        // Flarum locks the server to UTC, so without this a forum in Hanoi
        // would have its evening schedule land in the middle of its morning.
        $moment = Carbon::parse('2026-08-24T22:00:00+00:00');

        $this->assertSame(22, Daypart::indexFor($moment, 'UTC'));
        // 05:00 on Tuesday in Hanoi, which is slot 24 + 5.
        $this->assertSame(29, Daypart::indexFor($moment, 'Asia/Ho_Chi_Minh'));
    }

    #[Test]
    public function a_timezone_the_server_does_not_know_falls_back_rather_than_throwing(): void
    {
        // On the serving path of every page view, an exception is not an
        // option.
        $this->assertSame(9, Daypart::indexFor($this->mondayMorning(), 'Mars/Olympus_Mons'));
    }

    #[Test]
    public function a_mask_allows_exactly_the_hours_it_names(): void
    {
        $mask = Daypart::fromHours([9]);

        $this->assertTrue(Daypart::allows($mask, $this->mondayMorning()));
        $this->assertFalse(Daypart::allows($mask, Carbon::parse('2026-08-24T10:00:00+00:00')));
    }

    #[Test]
    public function a_mask_round_trips_through_every_hour(): void
    {
        for ($hour = 0; $hour < Daypart::HOURS; $hour++) {
            $mask = Daypart::fromHours([$hour]);

            $this->assertTrue(Daypart::isSet($mask, $hour), "hour $hour should be set");
            $this->assertFalse(Daypart::isSet($mask, ($hour + 1) % Daypart::HOURS));
        }
    }

    #[Test]
    public function a_mask_reads_left_to_right_from_monday_midnight(): void
    {
        // So that somebody debugging one by eye sees what they expect.
        $this->assertStringStartsWith('8', Daypart::fromHours([0]));
        $this->assertStringStartsWith('1', Daypart::fromHours([3]));
        $this->assertSame(Daypart::LENGTH, strlen(Daypart::fromHours([0])));
    }

    #[Test]
    public function every_hour_on_is_the_same_as_no_schedule(): void
    {
        $this->assertSame(Daypart::always(), Daypart::fromHours(range(0, Daypart::HOURS - 1)));

        foreach ([0, 9, 100, 167] as $hour) {
            $this->assertTrue(Daypart::isSet(Daypart::always(), $hour));
        }
    }

    #[Test]
    public function no_mask_means_always(): void
    {
        $this->assertTrue(Daypart::allows(null, $this->mondayMorning()));
        $this->assertTrue(Daypart::allows('', $this->mondayMorning()));
    }

    #[Test]
    public function a_malformed_mask_means_always_rather_than_never(): void
    {
        // A campaign that quietly stopped because its schedule was stored
        // wrong is far harder to diagnose than one that ran when it should not
        // have.
        $this->assertTrue(Daypart::allows('not-hexadecimal', $this->mondayMorning()));
        $this->assertTrue(Daypart::allows('ff', $this->mondayMorning()), 'too short');
        $this->assertTrue(Daypart::allows(str_repeat('f', 43), $this->mondayMorning()), 'too long');
    }

    #[Test]
    public function an_hour_outside_the_week_is_never_set(): void
    {
        $this->assertFalse(Daypart::isSet(Daypart::always(), -1));
        $this->assertFalse(Daypart::isSet(Daypart::always(), Daypart::HOURS));
    }

    #[Test]
    public function a_mask_of_nothing_allows_nothing(): void
    {
        $mask = Daypart::fromHours([]);

        $this->assertSame(str_repeat('0', Daypart::LENGTH), $mask);
        $this->assertFalse(Daypart::allows($mask, $this->mondayMorning()));
    }

    #[Test]
    public function office_hours_are_the_shape_this_is_for(): void
    {
        $hours = [];

        // Monday to Friday, nine until five.
        for ($day = 0; $day < 5; $day++) {
            for ($hour = 9; $hour < 17; $hour++) {
                $hours[] = $day * 24 + $hour;
            }
        }

        $mask = Daypart::fromHours($hours);

        $this->assertTrue(Daypart::allows($mask, Carbon::parse('2026-08-24T09:00:00+00:00')), 'Monday 9am');
        $this->assertTrue(Daypart::allows($mask, Carbon::parse('2026-08-28T16:00:00+00:00')), 'Friday 4pm');
        $this->assertFalse(Daypart::allows($mask, Carbon::parse('2026-08-24T08:00:00+00:00')), 'Monday 8am');
        $this->assertFalse(Daypart::allows($mask, Carbon::parse('2026-08-29T12:00:00+00:00')), 'Saturday noon');
    }
}
