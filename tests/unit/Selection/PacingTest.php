<?php

/*
 * This file is part of datlechin/flarum-placement.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\Placement\Tests\unit\Selection;

use Carbon\Carbon;
use Datlechin\Placement\Model\Campaign;
use Datlechin\Placement\Selection\Pacing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PacingTest extends TestCase
{
    /**
     * A ten-day flight, halfway through.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function campaign(array $overrides = []): array
    {
        return $overrides + [
            'pacing' => Campaign::PACING_EVEN,
            'is_house' => false,
            'starts_at' => '2026-01-01T00:00:00+00:00',
            'ends_at' => '2026-01-11T00:00:00+00:00',
            'max_impressions' => 1000,
            'max_clicks' => null,
            'impressions' => 0,
            'clicks' => 0,
        ];
    }

    private function halfway(): Carbon
    {
        return Carbon::parse('2026-01-06T00:00:00+00:00');
    }

    #[Test]
    public function a_campaign_on_schedule_is_served_freely(): void
    {
        $this->assertSame(1.0, Pacing::keepProbability(0.5, 0.5));
        $this->assertSame(1.0, Pacing::keepProbability(0.5, 0.4));
        $this->assertSame(1.0, Pacing::keepProbability(1.0, 0.0));
    }

    #[Test]
    public function a_campaign_ahead_of_schedule_is_thinned_in_proportion(): void
    {
        // Halfway through the flight but three quarters spent: served two
        // times in three.
        $this->assertEqualsWithDelta(2 / 3, Pacing::keepProbability(0.5, 0.75), 0.0001);

        // Twice as far ahead: served half as often.
        $this->assertEqualsWithDelta(0.5, Pacing::keepProbability(0.25, 0.5), 0.0001);
    }

    #[Test]
    public function a_campaign_is_never_thinned_out_completely(): void
    {
        // A campaign throttled to nothing can never recover: if the forum goes
        // quiet it stays at nothing while its flight runs out.
        $this->assertSame(Pacing::FLOOR, Pacing::keepProbability(0.0, 1.0));
        $this->assertGreaterThan(0.0, Pacing::keepProbability(0.001, 0.9));
    }

    #[Test]
    public function pacing_needs_both_a_flight_and_a_cap(): void
    {
        // Without a schedule to be ahead of there is nothing to pace against.
        // Treating a missing value as zero would throttle every campaign that
        // has no end date, which is most of them.
        $this->assertSame(1.0, Pacing::keepProbability(null, 0.9));
        $this->assertSame(1.0, Pacing::keepProbability(0.1, null));
        $this->assertSame(1.0, Pacing::keepProbability(null, null));
    }

    #[Test]
    public function a_campaign_set_to_deliver_as_fast_as_possible_is_never_paced(): void
    {
        $campaign = $this->campaign(['pacing' => Campaign::PACING_ASAP, 'impressions' => 999]);

        $this->assertTrue(Pacing::shouldServe($campaign, $this->halfway(), fn () => 0.99));
    }

    #[Test]
    public function a_house_campaign_is_never_paced(): void
    {
        // Its whole job is to fill space nobody paid for; thinning it out would
        // leave holes on the page for no benefit to anybody.
        $campaign = $this->campaign(['is_house' => true, 'impressions' => 999]);

        $this->assertTrue(Pacing::shouldServe($campaign, $this->halfway(), fn () => 0.99));
    }

    #[Test]
    public function a_campaign_behind_schedule_is_always_served(): void
    {
        // 20% spent, 50% elapsed.
        $campaign = $this->campaign(['impressions' => 200]);

        $this->assertTrue(Pacing::shouldServe($campaign, $this->halfway(), fn () => 0.99));
    }

    #[Test]
    public function a_campaign_ahead_of_schedule_is_served_sometimes_and_not_others(): void
    {
        // 75% spent, 50% elapsed: kept two times in three.
        $campaign = $this->campaign(['impressions' => 750]);

        $this->assertTrue(Pacing::shouldServe($campaign, $this->halfway(), fn () => 0.5));
        $this->assertFalse(Pacing::shouldServe($campaign, $this->halfway(), fn () => 0.9));
    }

    #[Test]
    public function delivery_is_measured_against_the_impression_cap_when_there_is_one(): void
    {
        $campaign = $this->campaign(['impressions' => 250, 'max_impressions' => 1000]);

        $this->assertEqualsWithDelta(0.25, Pacing::deliveredFraction($campaign), 0.0001);
    }

    #[Test]
    public function a_click_cap_is_used_when_there_is_no_impression_cap(): void
    {
        $campaign = $this->campaign(['max_impressions' => null, 'max_clicks' => 50, 'clicks' => 10]);

        $this->assertEqualsWithDelta(0.2, Pacing::deliveredFraction($campaign), 0.0001);
    }

    #[Test]
    public function a_campaign_with_no_cap_has_no_delivered_fraction(): void
    {
        $campaign = $this->campaign(['max_impressions' => null, 'max_clicks' => null, 'impressions' => 5000]);

        $this->assertNull(Pacing::deliveredFraction($campaign));
    }

    #[Test]
    public function a_zero_cap_is_treated_as_no_cap_rather_than_as_instantly_full(): void
    {
        $campaign = $this->campaign(['max_impressions' => 0, 'max_clicks' => null]);

        $this->assertNull(Pacing::deliveredFraction($campaign));
    }

    #[Test]
    public function elapsed_time_is_clamped_to_the_flight(): void
    {
        $campaign = $this->campaign();

        $this->assertSame(0.0, Pacing::elapsedFraction($campaign, Carbon::parse('2025-12-01T00:00:00+00:00')));
        $this->assertEqualsWithDelta(0.5, Pacing::elapsedFraction($campaign, $this->halfway()), 0.0001);
        $this->assertSame(1.0, Pacing::elapsedFraction($campaign, Carbon::parse('2026-02-01T00:00:00+00:00')));
    }

    #[Test]
    public function a_campaign_without_a_bounded_flight_has_no_elapsed_fraction(): void
    {
        $this->assertNull(Pacing::elapsedFraction($this->campaign(['ends_at' => null]), $this->halfway()));
        $this->assertNull(Pacing::elapsedFraction($this->campaign(['starts_at' => null]), $this->halfway()));
        $this->assertNull(Pacing::elapsedFraction(
            $this->campaign(['starts_at' => '2026-01-01T00:00:00+00:00', 'ends_at' => '2026-01-01T00:00:00+00:00']),
            $this->halfway()
        ));
    }
}
