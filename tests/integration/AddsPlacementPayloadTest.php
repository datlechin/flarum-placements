<?php

/*
 * This file is part of datlechin/flarum-placement.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\Placement\Tests\integration;

use Datlechin\Placement\Support\Permissions;
use Flarum\Group\Group;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;

/**
 * The payload is the only thing that puts an advert on a page.
 *
 * The frontend ships in every build and does nothing at all unless it finds
 * `app.data.placement`, so every rule about who is served — ad-free groups,
 * crawlers, campaigns that are not live — is enforced here and nowhere else.
 */
class AddsPlacementPayloadTest extends TestCase
{
    use RetrievesAuthorizedUsers;
    use SeedsCampaigns;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('datlechin-placement');

        $this->prepareDatabase([
            'users' => [$this->normalUser()],
            'group_permission' => [],
            ...$this->campaignSeed(),
        ]);
    }

    /**
     * The JSON Flarum hands to the frontend at boot.
     *
     * @return array<string, mixed>
     */
    protected function payload(ResponseInterface $response): array
    {
        $body = (string) $response->getBody();

        $this->assertMatchesRegularExpression(
            '/<script id="flarum-json-payload" type="application\/json">(.*?)<\/script>/s',
            $body,
            'the page carried no frontend payload'
        );

        preg_match('/<script id="flarum-json-payload" type="application\/json">(.*?)<\/script>/s', $body, $matches);

        return json_decode(html_entity_decode($matches[1], ENT_QUOTES), true) ?? [];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function placement(?int $authenticatedAs = null, string $userAgent = 'Mozilla/5.0'): ?array
    {
        $request = $this->request('GET', '/', $authenticatedAs ? ['authenticatedAs' => $authenticatedAs] : [])
            ->withHeader('User-Agent', $userAgent);

        return $this->payload($this->send($request))['placement'] ?? null;
    }

    #[Test]
    public function a_guest_is_served_the_slots_a_live_campaign_reaches(): void
    {
        $placement = $this->placement();

        $this->assertNotNull($placement, 'nothing was written for an ordinary visitor');
        $this->assertFalse($placement['demo']);
        $this->assertArrayHasKey('index_above_list', $placement['slots']);
    }

    #[Test]
    public function the_candidate_carries_what_the_client_needs_to_draw_it(): void
    {
        $candidates = $this->placement()['slots']['index_above_list']['candidates'];

        $this->assertCount(1, $candidates);
        $this->assertSame('image', $candidates[0]['type']);
        $this->assertSame('https://example.com/offer', $candidates[0]['url']);
        $this->assertSame(10, $candidates[0]['weight']);
        $this->assertArrayHasKey('asset', $candidates[0]['payload']);
    }

    #[Test]
    public function a_slot_with_nothing_eligible_is_left_out_entirely(): void
    {
        // Rather than sent empty. It saves the bytes on the critical path, and
        // the client reserves no space for a key it cannot find.
        $slots = $this->placement()['slots'];

        $this->assertArrayNotHasKey('discussion_sidebar', $slots);
    }

    #[Test]
    public function somebody_whose_group_was_granted_ad_free_browsing_gets_no_payload_at_all(): void
    {
        // Not an empty payload: no reserved space, no beacon, and nothing in
        // view-source describing inventory to somebody who cannot see it.
        $this->database()->table('group_permission')->insert([
            'group_id' => Group::MEMBER_ID,
            'permission' => Permissions::VIEW_WITHOUT_ADS,
        ]);

        $this->assertNull($this->placement(2));
    }

    #[Test]
    public function a_crawler_is_told_nothing(): void
    {
        // Flarum's SEO body sits inside <noscript>, so a plain crawler sees no
        // slots — but Googlebot renders the whole SPA and would fire every
        // beacon on the page. Serving it nothing is what stops this extension
        // manufacturing invalid traffic on a publisher's behalf.
        $this->assertNull($this->placement(null, 'Googlebot/2.1 (+http://www.google.com/bot.html)'));
    }

    #[Test]
    public function a_request_with_no_user_agent_at_all_is_treated_as_a_crawler(): void
    {
        $this->assertNull($this->placement(null, ''));
    }

    #[Test]
    public function a_paused_campaign_serves_nothing(): void
    {
        $this->database()->table('placement_campaigns')->update(['status' => 'paused']);

        $this->assertNull($this->placement());
    }

    #[Test]
    public function a_campaign_whose_flight_has_ended_serves_nothing(): void
    {
        // Computed from the dates on every request, because a large share of
        // self-hosted installs have no working scheduler and a cron-driven
        // expiry would simply never fire.
        $this->database()->table('placement_campaigns')->update(['ends_at' => '2020-01-01 00:00:00']);

        $this->assertNull($this->placement());
    }

    #[Test]
    public function a_campaign_at_its_impression_cap_serves_nothing(): void
    {
        $this->database()->table('placement_campaigns')->update([
            'max_impressions' => 100,
            'impressions' => 100,
        ]);

        $this->assertNull($this->placement());
    }

    #[Test]
    public function a_creative_that_has_not_been_approved_serves_nothing(): void
    {
        $this->database()->table('placement_creatives')->update(['status' => 'pending']);

        $this->assertNull($this->placement());
    }

    #[Test]
    public function targeting_is_applied_to_the_actual_viewer(): void
    {
        $this->database()->table('placement_campaign_rules')->insert([
            'campaign_id' => 1,
            'dimension' => 'visitor',
            'operator' => 'is',
            'value' => 'member',
        ]);

        $this->assertNull($this->placement(), 'a guest was served a members-only campaign');
        $this->assertNotNull($this->placement(2), 'a member was not served a members-only campaign');
    }

    #[Test]
    public function a_slot_an_administrator_switched_off_is_not_served(): void
    {
        $this->database()->table('placement_settings')->insert([
            'key' => 'index_above_list',
            'enabled' => false,
        ]);

        $this->assertNull($this->placement());
    }
}
