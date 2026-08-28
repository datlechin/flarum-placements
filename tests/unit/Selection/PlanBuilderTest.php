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
use Datlechin\Placements\Model\Campaign;
use Datlechin\Placements\Placement;
use Datlechin\Placements\PlacementRegistry;
use Datlechin\Placements\PlacementServiceProvider;
use Datlechin\Placements\Selection\PlanBuilder;
use Datlechin\Placements\Selection\Daypart;
use Datlechin\Placements\Selection\PlanSource;
use Datlechin\Placements\Targeting\Dimension\GroupDimension;
use Datlechin\Placements\Targeting\Dimension\RouteDimension;
use Datlechin\Placements\Targeting\Dimension\VisitorDimension;
use Datlechin\Placements\Targeting\DimensionInterface as D;
use Datlechin\Placements\Tests\unit\ConnectsModels;
use Datlechin\Placements\Tests\unit\Targeting\MakesContexts;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Container\Container;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PlanBuilderTest extends TestCase
{
    use ConnectsModels;
    use MakesContexts;

    protected function setUp(): void
    {
        $this->connectModels();
    }

    /**
     * A source with the campaigns already in its cache, so nothing queries.
     */
    private function builder(array $campaigns, ?array $placements = null): PlanBuilder
    {
        $cache = new Repository(new ArrayStore());
        $cache->forever(PlanSource::CACHE_KEY, $campaigns);

        $container = new Container();
        $container->singleton(PlacementServiceProvider::DIMENSIONS, fn () => [
            VisitorDimension::class,
            GroupDimension::class,
            RouteDimension::class,
        ]);

        $registry = new PlacementRegistry($placements ?? [
            new Placement(key: 'notice'),
            new Placement(key: 'index_sidebar'),
            new Placement(key: 'header', allowedTypes: ['text']),
        ]);

        return new PlanBuilder(new PlanSource($cache), $registry, $container, null, 'UTC');
    }

    private function campaign(array $overrides = [], array $creatives = [], array $rules = []): array
    {
        return array_merge([
            'id' => 1,
            'tier' => Campaign::TIER_STANDARD,
            'is_house' => false,
            'status' => Campaign::STATUS_ACTIVE,
            'starts_at' => null,
            'ends_at' => null,
            'daypart_mask' => null,
            'pacing' => Campaign::PACING_ASAP,
            'frequency_cap' => null,
            'frequency_window' => Campaign::WINDOW_DAY,
            'max_impressions' => null,
            'max_clicks' => null,
            'impressions' => 0,
            'clicks' => 0,
            'rules' => $rules,
            'creatives' => $creatives ?: [$this->creative()],
        ], $overrides);
    }

    private function creative(array $overrides = []): array
    {
        return array_merge([
            'id' => 10,
            'type' => 'image',
            'weight' => 10,
            'url' => 'https://example.com',
            'label' => null,
            'payload' => ['asset' => 'a.png'],
            'placements' => ['notice' => null],
        ], $overrides);
    }

    #[Test]
    public function an_eligible_creative_becomes_a_candidate_in_its_slot(): void
    {
        $slots = $this->builder([$this->campaign()])->forContext($this->context());

        $this->assertSame(['notice'], array_keys($slots));
        $this->assertCount(1, $slots['notice']);
        $this->assertSame(10, $slots['notice'][0]['creative']);
        $this->assertSame(1, $slots['notice'][0]['campaign']);
        $this->assertSame('https://example.com', $slots['notice'][0]['url']);
    }

    #[Test]
    public function a_campaign_that_is_not_live_contributes_nothing(): void
    {
        $ended = $this->campaign(['ends_at' => '2026-01-01T00:00:00+00:00']);

        $slots = $this->builder([$ended])->forContext($this->context(), null, Carbon::parse('2026-06-01'));

        $this->assertSame([], $slots);
    }

    #[Test]
    public function a_paused_campaign_contributes_nothing(): void
    {
        $paused = $this->campaign(['status' => Campaign::STATUS_PAUSED]);

        $this->assertSame([], $this->builder([$paused])->forContext($this->context()));
    }

    #[Test]
    public function a_campaign_at_its_cap_contributes_nothing(): void
    {
        $capped = $this->campaign(['max_impressions' => 100, 'impressions' => 100]);

        $this->assertSame([], $this->builder([$capped])->forContext($this->context()));
    }

    #[Test]
    public function targeting_is_applied_against_this_viewer(): void
    {
        $membersOnly = $this->campaign(rules: [
            ['dimension' => 'visitor', 'operator' => D::IS, 'value' => 'member'],
        ]);

        $builder = $this->builder([$membersOnly]);

        $this->assertSame([], $builder->forContext($this->context()));
        $this->assertArrayHasKey('notice', $builder->forContext($this->context($this->member())));
    }

    #[Test]
    public function a_slot_the_registry_does_not_know_is_skipped(): void
    {
        // An assignment left behind by an extension that has since been
        // disabled. Inert rather than fatal, which is what we want.
        $orphaned = $this->campaign(creatives: [
            $this->creative(['placements' => ['acme.gone' => null, 'notice' => null]]),
        ]);

        $this->assertSame(['notice'], array_keys($this->builder([$orphaned])->forContext($this->context())));
    }

    #[Test]
    public function a_slot_that_is_switched_off_is_skipped(): void
    {
        $campaign = $this->campaign(creatives: [
            $this->creative(['placements' => ['notice' => null, 'index_sidebar' => null]]),
        ]);

        $slots = $this->builder([$campaign])->forContext($this->context(), ['index_sidebar']);

        $this->assertSame(['index_sidebar'], array_keys($slots));
    }

    #[Test]
    public function a_slot_refuses_a_creative_type_it_does_not_accept(): void
    {
        $campaign = $this->campaign(creatives: [
            $this->creative(['type' => 'image', 'placements' => ['header' => null]]),
        ]);

        $this->assertSame([], $this->builder([$campaign])->forContext($this->context()));
    }

    #[Test]
    public function an_assignment_weight_overrides_the_creative_weight_in_that_slot(): void
    {
        $campaign = $this->campaign(creatives: [
            $this->creative(['weight' => 10, 'placements' => ['notice' => 90, 'index_sidebar' => null]]),
        ]);

        $slots = $this->builder([$campaign])->forContext($this->context());

        $this->assertSame(90, $slots['notice'][0]['weight']);
        $this->assertSame(10, $slots['index_sidebar'][0]['weight']);
    }

    #[Test]
    public function a_weight_is_never_below_one(): void
    {
        // Zero would silently drop the creative out of the weighted draw,
        // which is what disabling an assignment is for.
        $campaign = $this->campaign(creatives: [
            $this->creative(['weight' => 0, 'placements' => ['notice' => 0]]),
        ]);

        $this->assertSame(1, $this->builder([$campaign])->forContext($this->context())['notice'][0]['weight']);
    }

    #[Test]
    public function candidates_arrive_best_tier_first(): void
    {
        // So the client takes the leading run and never has to think about
        // tiers again.
        $house = $this->campaign(['id' => 1, 'tier' => Campaign::TIER_HOUSE], [$this->creative(['id' => 1])]);
        $sponsor = $this->campaign(['id' => 2, 'tier' => Campaign::TIER_SPONSORSHIP], [$this->creative(['id' => 2])]);
        $standard = $this->campaign(['id' => 3, 'tier' => Campaign::TIER_STANDARD], [$this->creative(['id' => 3])]);

        $slots = $this->builder([$house, $sponsor, $standard])->forContext($this->context());

        $this->assertSame([2, 3, 1], array_column($slots['notice'], 'creative'));
    }

    #[Test]
    public function one_creative_can_run_in_several_slots(): void
    {
        $campaign = $this->campaign(creatives: [
            $this->creative(['placements' => ['notice' => null, 'index_sidebar' => null]]),
        ]);

        $slots = $this->builder([$campaign])->forContext($this->context());

        $this->assertSame(['notice', 'index_sidebar'], array_keys($slots));
    }

    #[Test]
    public function nothing_at_all_is_an_empty_result_rather_than_empty_slots(): void
    {
        $this->assertSame([], $this->builder([])->forContext($this->context()));
    }

    #[Test]
    public function the_viewer_is_resolved_once_for_every_server_side_axis(): void
    {
        $viewer = $this->builder([])->resolveViewer($this->context(null, ['routeName' => 'index']));

        $this->assertSame(['visitor', 'group', 'route'], array_keys($viewer));
        $this->assertSame('guest', $viewer['visitor']);
        $this->assertSame('index', $viewer['route']);
    }

    #[Test]
    public function a_campaign_outside_its_scheduled_hours_serves_nothing(): void
    {
        // Monday 09:00 UTC; the mask allows only Monday 10:00.
        $scheduled = $this->campaign(['daypart_mask' => Daypart::fromHours([10])]);

        $slots = $this->builder([$scheduled])->forContext($this->context(), null, Carbon::parse('2026-08-24T09:00:00+00:00'));

        $this->assertSame([], $slots);
    }

    #[Test]
    public function a_campaign_inside_its_scheduled_hours_serves(): void
    {
        $scheduled = $this->campaign(['daypart_mask' => Daypart::fromHours([9])]);

        $slots = $this->builder([$scheduled])->forContext($this->context(), null, Carbon::parse('2026-08-24T09:00:00+00:00'));

        $this->assertArrayHasKey('notice', $slots);
    }

    #[Test]
    public function a_malformed_schedule_serves_rather_than_stopping(): void
    {
        // A campaign that quietly stopped because its schedule was stored
        // wrong is far harder to diagnose than one that ran when it should
        // not have.
        $scheduled = $this->campaign(['daypart_mask' => 'nonsense']);

        $this->assertArrayHasKey('notice', $this->builder([$scheduled])->forContext($this->context()));
    }

    #[Test]
    public function a_campaign_far_ahead_of_its_pace_is_thinned_out(): void
    {
        // Halfway through the flight, cap almost spent. Pacing is the only
        // non-deterministic check, so it runs last: everything above either
        // matches or does not.
        $ahead = $this->campaign([
            'pacing' => Campaign::PACING_EVEN,
            'starts_at' => '2026-01-01T00:00:00+00:00',
            'ends_at' => '2026-01-11T00:00:00+00:00',
            'max_impressions' => 1000,
            'impressions' => 950,
        ]);

        $builder = $this->builder([$ahead]);
        $halfway = Carbon::parse('2026-01-06T00:00:00+00:00');

        $served = 0;

        for ($i = 0; $i < 200; $i++) {
            $served += $builder->forContext($this->context(), null, $halfway) === [] ? 0 : 1;
        }

        // Kept roughly 0.5/0.95 of the time, so a long way short of always and
        // a long way short of never.
        $this->assertGreaterThan(50, $served, 'a paced campaign should still be served sometimes');
        $this->assertLessThan(190, $served, 'a campaign this far ahead should be thinned out');
    }

    #[Test]
    public function a_campaign_delivering_as_fast_as_possible_is_never_thinned(): void
    {
        $fast = $this->campaign([
            'pacing' => Campaign::PACING_ASAP,
            'frequency_cap' => null,
            'frequency_window' => Campaign::WINDOW_DAY,
            'starts_at' => '2026-01-01T00:00:00+00:00',
            'ends_at' => '2026-01-11T00:00:00+00:00',
            'max_impressions' => 1000,
            'impressions' => 999,
        ]);

        $builder = $this->builder([$fast]);
        $halfway = Carbon::parse('2026-01-06T00:00:00+00:00');

        for ($i = 0; $i < 20; $i++) {
            $this->assertArrayHasKey('notice', $builder->forContext($this->context(), null, $halfway));
        }
    }
}
