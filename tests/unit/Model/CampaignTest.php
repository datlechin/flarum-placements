<?php

/*
 * This file is part of datlechin/flarum-placement.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\Placement\Tests\unit\Model;

use Carbon\Carbon;
use Datlechin\Placement\Model\Campaign;
use Datlechin\Placement\Tests\unit\ConnectsModels;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class CampaignTest extends TestCase
{
    use ConnectsModels;

    protected function setUp(): void
    {
        $this->connectModels();
    }

    private function campaign(array $attributes = []): Campaign
    {
        $campaign = new Campaign();
        $campaign->forceFill(array_merge([
            'status' => Campaign::STATUS_ACTIVE,
            'is_house' => false,
            'impressions' => 0,
            'clicks' => 0,
        ], $attributes));

        return $campaign;
    }

    #[Test]
    #[DataProvider('statusesThatVeto')]
    public function an_administrator_who_stopped_a_campaign_means_it_whatever_the_dates_say(string $status): void
    {
        $campaign = $this->campaign([
            'status' => $status,
            'starts_at' => Carbon::parse('2026-01-01'),
            'ends_at' => Carbon::parse('2027-01-01'),
        ]);

        $this->assertFalse($campaign->isLive(Carbon::parse('2026-06-01')));
    }

    public static function statusesThatVeto(): array
    {
        return [
            'draft' => [Campaign::STATUS_DRAFT],
            'paused' => [Campaign::STATUS_PAUSED],
            'archived' => [Campaign::STATUS_ARCHIVED],
        ];
    }

    #[Test]
    public function a_campaign_with_no_dates_runs(): void
    {
        $this->assertTrue($this->campaign()->isLive(Carbon::parse('2026-06-01')));
    }

    #[Test]
    public function it_does_not_run_before_it_starts(): void
    {
        $campaign = $this->campaign(['starts_at' => Carbon::parse('2026-07-01')]);

        $this->assertFalse($campaign->isLive(Carbon::parse('2026-06-30 23:59:59')));
        $this->assertTrue($campaign->isLive(Carbon::parse('2026-07-01 00:00:00')));
    }

    #[Test]
    public function the_end_date_is_exclusive(): void
    {
        // A campaign booked to the 1st has not been booked to include the 1st.
        // Getting this inclusive is how an advertiser gets a day they did not
        // pay for, every time.
        $campaign = $this->campaign(['ends_at' => Carbon::parse('2026-07-01 00:00:00')]);

        $this->assertTrue($campaign->isLive(Carbon::parse('2026-06-30 23:59:59')));
        $this->assertFalse($campaign->isLive(Carbon::parse('2026-07-01 00:00:00')));
    }

    #[Test]
    public function it_stops_when_it_reaches_its_impression_cap(): void
    {
        $campaign = $this->campaign(['max_impressions' => 1000, 'impressions' => 1000]);

        $this->assertTrue($campaign->hasReachedACap());
        $this->assertFalse($campaign->isLive());
    }

    #[Test]
    public function it_stops_when_it_reaches_its_click_cap(): void
    {
        $campaign = $this->campaign(['max_clicks' => 50, 'clicks' => 51]);

        $this->assertTrue($campaign->hasReachedACap());
    }

    #[Test]
    public function a_campaign_below_both_caps_keeps_running(): void
    {
        $campaign = $this->campaign([
            'max_impressions' => 1000, 'impressions' => 999,
            'max_clicks' => 50, 'clicks' => 49,
        ]);

        $this->assertFalse($campaign->hasReachedACap());
    }

    #[Test]
    public function a_house_campaign_ignores_caps_entirely(): void
    {
        // Its whole job is to fill space nobody paid for. A house ad that
        // capped out would leave holes on the page.
        $campaign = $this->campaign([
            'is_house' => true,
            'max_impressions' => 10,
            'impressions' => 100_000,
        ]);

        $this->assertFalse($campaign->hasReachedACap());
        $this->assertTrue($campaign->isLive());
    }

    #[Test]
    public function elapsed_fraction_measures_progress_through_the_flight(): void
    {
        $campaign = $this->campaign([
            'starts_at' => Carbon::parse('2026-01-01'),
            'ends_at' => Carbon::parse('2026-01-11'),
        ]);

        $this->assertSame(0.0, $campaign->elapsedFraction(Carbon::parse('2026-01-01')));
        $this->assertSame(0.5, $campaign->elapsedFraction(Carbon::parse('2026-01-06')));
        $this->assertSame(1.0, $campaign->elapsedFraction(Carbon::parse('2026-01-11')));
    }

    #[Test]
    public function elapsed_fraction_is_clamped_outside_the_flight(): void
    {
        $campaign = $this->campaign([
            'starts_at' => Carbon::parse('2026-01-01'),
            'ends_at' => Carbon::parse('2026-01-11'),
        ]);

        $this->assertSame(0.0, $campaign->elapsedFraction(Carbon::parse('2025-12-01')));
        $this->assertSame(1.0, $campaign->elapsedFraction(Carbon::parse('2026-02-01')));
    }

    #[Test]
    public function a_campaign_without_a_bounded_flight_cannot_be_paced(): void
    {
        // Even delivery needs something to be even against. Returning 0 here
        // instead of null would make an unbounded campaign look permanently
        // behind schedule and never throttle.
        $this->assertNull($this->campaign()->elapsedFraction());
        $this->assertNull($this->campaign(['starts_at' => Carbon::parse('2026-01-01')])->elapsedFraction());
        $this->assertNull($this->campaign(['ends_at' => Carbon::parse('2026-01-01')])->elapsedFraction());
    }

    #[Test]
    public function a_flight_that_starts_and_ends_together_cannot_be_paced_either(): void
    {
        $campaign = $this->campaign([
            'starts_at' => Carbon::parse('2026-01-01'),
            'ends_at' => Carbon::parse('2026-01-01'),
        ]);

        $this->assertNull($campaign->elapsedFraction(Carbon::parse('2026-01-01')));
    }
}
