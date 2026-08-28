<?php

/*
 * This file is part of datlechin/flarum-placements.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\Placements\Tests\integration;

use Datlechin\Placements\Measurement\Recorder;
use Datlechin\Placements\Model\Campaign;
use Datlechin\Placements\Selection\PlanSource;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;

/**
 * A cap reached *while serving*, which is the only way a cap is ever reached.
 *
 * AddsPlacementPayloadTest already covers a campaign that is over its cap
 * before anything is served. That proves `Liveness::capped()` reads the number
 * correctly, and it cannot prove the cap ever trips: the plan is built once
 * the campaign is already capped, so the cached copy is right from the start.
 *
 * The real sequence is the other way round. The plan is cached while the
 * campaign is live, with its running totals baked in, and the counters then
 * move underneath it -- by `increment()` on a query builder, which fires no
 * model events, so none of the invalidation hooks run.
 */
class StopsAtTheCapTest extends TestCase
{
    use RetrievesAuthorizedUsers;
    use SeedsCampaigns;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('datlechin-placements');

        $this->prepareDatabase([
            'users' => [$this->normalUser()],
            'group_permission' => [],
            ...$this->campaignSeed(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(ResponseInterface $response): array
    {
        preg_match(
            '/<script id="flarum-json-payload" type="application\/json">(.*?)<\/script>/s',
            (string) $response->getBody(),
            $matches
        );

        return json_decode(html_entity_decode($matches[1] ?? '{}', ENT_QUOTES), true) ?? [];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function placement(): ?array
    {
        $request = $this->request('GET', '/')->withHeader('User-Agent', 'Mozilla/5.0');

        return $this->payload($this->send($request))['placement'] ?? null;
    }

    private function countImpressions(int $times): void
    {
        /** @var Recorder $recorder */
        $recorder = $this->app()->getContainer()->make(Recorder::class);

        for ($i = 0; $i < $times; $i++) {
            $recorder->record(
                ['campaign' => 1, 'creative' => 1, 'placement' => 'index_above_list', 'device' => 'desktop'],
                'impression'
            );
        }

        // What the scheduled command does every minute.
        $recorder->flush();
    }

    #[Test]
    public function a_campaign_that_reaches_its_cap_while_serving_stops(): void
    {
        $this->database()->table('placement_campaigns')->update(['max_impressions' => 2, 'impressions' => 0]);

        // Warms the cached plan while the campaign is still live.
        $this->assertNotNull($this->placement(), 'it should serve before the cap is reached');

        $this->countImpressions(5);

        $this->assertSame(
            5,
            (int) $this->database()->table('placement_campaigns')->where('id', 1)->value('impressions'),
            'the running total should have moved'
        );

        $this->assertNull($this->placement(), 'a campaign past its cap was still being served');
    }

    #[Test]
    public function a_campaign_that_reaches_its_click_cap_while_serving_stops(): void
    {
        $this->database()->table('placement_campaigns')->update(['max_clicks' => 1, 'clicks' => 0]);

        $this->assertNotNull($this->placement());

        /** @var Recorder $recorder */
        $recorder = $this->app()->getContainer()->make(Recorder::class);
        $recorder->record(
            ['campaign' => 1, 'creative' => 1, 'placement' => 'index_above_list', 'device' => 'desktop'],
            'click'
        );
        $recorder->flush();

        $this->assertNull($this->placement());
    }

    /**
     * Still serving is the right answer here, and the point of the test is the
     * reason: a campaign with no cap and no even pacing has nothing about it
     * that counting can change, so the plan must not be dropped on every
     * flush for the rest of the forum's life.
     */
    #[Test]
    public function counting_against_an_uncapped_campaign_leaves_the_plan_alone(): void
    {
        $this->database()->table('placement_campaigns')->update([
            'max_impressions' => null,
            'max_clicks' => null,
            'pacing' => Campaign::PACING_ASAP,
        ]);

        $this->assertNotNull($this->placement());

        /** @var PlanSource $plan */
        $plan = $this->app()->getContainer()->make(PlanSource::class);
        $before = $plan->campaigns();

        $this->countImpressions(3);

        // Same array, still cached: nothing rebuilt it.
        $this->assertSame($before, $plan->campaigns());
        $this->assertNotNull($this->placement());
    }
}
