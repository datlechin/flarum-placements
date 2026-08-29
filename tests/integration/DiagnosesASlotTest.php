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

use Datlechin\Placements\Model\Campaign;
use Datlechin\Placements\Model\Creative;
use Datlechin\Placements\Selection\Daypart;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * "Why is my advert not showing?"
 *
 * Every gate that could answer it was a bare `continue`, and
 * `RuleEvaluator::firstFailure()` -- written for exactly this, and documented
 * as such -- was called only by `matches()`, which threw the answer away.
 *
 * The order matters as much as the answers: the first refusal reported has to
 * be the first refusal that happens, or the diagnosis sends somebody to fix
 * the wrong thing.
 */
class DiagnosesASlotTest extends TestCase
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
    private function diagnose(string $placement = 'index_above_list', int $creative = 1, int $as = 1): array
    {
        $response = $this->send(
            $this->request('GET', '/api/placements/diagnose', ['authenticatedAs' => $as])
                ->withQueryParams(['creative' => $creative, 'placement' => $placement])
        );

        $this->assertSame(200, $response->getStatusCode());

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function updateCampaign(array $attributes): void
    {
        $this->database()->table('placement_campaigns')->where('id', 1)->update($attributes);
    }

    #[Test]
    public function a_creative_that_would_serve_says_so(): void
    {
        $this->assertSame('eligible', $this->diagnose()['reason']);
    }

    #[Test]
    public function a_slot_the_creative_is_not_assigned_to(): void
    {
        $this->assertSame('not_assigned', $this->diagnose('index_sidebar')['reason']);
    }

    #[Test]
    public function a_slot_nothing_declares(): void
    {
        $this->assertSame('slot_unknown', $this->diagnose('not_a_real_slot')['reason']);
    }

    #[Test]
    public function a_slot_the_administrator_switched_off(): void
    {
        $this->database()->table('placement_settings')->insert(['key' => 'index_above_list', 'enabled' => false]);

        $this->assertSame('slot_disabled', $this->diagnose()['reason']);
    }

    #[Test]
    public function a_creative_nobody_approved(): void
    {
        $this->database()->table('placement_creatives')->where('id', 1)->update(['status' => Creative::STATUS_PENDING]);

        $this->assertSame('not_approved', $this->diagnose()['reason']);
    }

    #[Test]
    public function a_campaign_still_in_draft(): void
    {
        $this->updateCampaign(['status' => Campaign::STATUS_DRAFT]);

        $this->assertSame('campaign_not_running', $this->diagnose()['reason']);
    }

    #[Test]
    public function a_campaign_whose_flight_has_ended(): void
    {
        $this->updateCampaign([
            'starts_at' => '2020-01-01 00:00:00',
            'ends_at' => '2020-02-01 00:00:00',
        ]);

        $this->assertSame('campaign_not_live', $this->diagnose()['reason']);
    }

    /**
     * A cap is worth telling apart from a finished flight: one is spent, the
     * other is over, and the fix is different.
     */
    #[Test]
    public function a_campaign_that_has_spent_its_cap(): void
    {
        $this->updateCampaign(['max_impressions' => 10, 'impressions' => 10]);

        $this->assertSame('campaign_capped', $this->diagnose()['reason']);
    }

    #[Test]
    public function a_campaign_outside_its_scheduled_hours(): void
    {
        // Every hour off, so the answer cannot depend on when the suite runs.
        $this->updateCampaign(['daypart_mask' => str_repeat('0', Daypart::LENGTH)]);

        $this->assertSame('outside_daypart', $this->diagnose()['reason']);
    }

    /**
     * The gate whose answer was being computed and discarded: which axis
     * refused, not merely that one did.
     */
    #[Test]
    public function a_targeting_rule_says_which_axis_refused(): void
    {
        $this->database()->table('placement_campaign_rules')->insert([
            'id' => 1,
            'campaign_id' => 1,
            'dimension' => 'visitor',
            'operator' => 'is',
            'value' => 'guest',
        ]);

        $verdict = $this->diagnose();

        $this->assertSame('targeted_out', $verdict['reason']);
        $this->assertSame('visitor', $verdict['dimension'], 'the administrator is signed in, so the guest rule is what refused');
    }

    /**
     * The order is the point: a creative that is both unapproved and
     * unassigned is unassigned first, because that is what the serving path
     * notices first.
     */
    #[Test]
    public function the_first_refusal_reported_is_the_first_one_that_happens(): void
    {
        $this->database()->table('placement_creatives')->where('id', 1)->update(['status' => Creative::STATUS_PENDING]);

        $this->assertSame('not_assigned', $this->diagnose('index_sidebar')['reason']);
    }

    #[Test]
    public function an_unknown_creative_is_a_bad_request(): void
    {
        $response = $this->send(
            $this->request('GET', '/api/placements/diagnose', ['authenticatedAs' => 1])
                ->withQueryParams(['creative' => 999, 'placement' => 'index_above_list'])
        );

        $this->assertSame(400, $response->getStatusCode());
    }

    #[Test]
    public function an_ordinary_member_cannot_ask(): void
    {
        $response = $this->send(
            $this->request('GET', '/api/placements/diagnose', ['authenticatedAs' => 2])
                ->withQueryParams(['creative' => 1, 'placement' => 'index_above_list'])
        );

        $this->assertSame(403, $response->getStatusCode());
    }
}
