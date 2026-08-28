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

use Datlechin\Placements\Support\Permissions;
use Flarum\Group\Group;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;

/**
 * The endpoints an administrator manages advertising through.
 *
 * A campaign row says what an advertiser is paying and who they are, so none
 * of this is ever readable by an ordinary member. That is the part worth
 * testing here rather than the CRUD.
 */
class PlacementApiTest extends TestCase
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
     * @param  array<string, mixed>  $options
     */
    protected function api(string $method, string $path, array $options = []): ResponseInterface
    {
        return $this->send($this->request($method, "/api$path", $options));
    }

    /**
     * @return array<string, mixed>
     */
    protected function json(ResponseInterface $response): array
    {
        return json_decode((string) $response->getBody(), true) ?? [];
    }

    #[Test]
    public function a_guest_cannot_read_the_campaign_list(): void
    {
        $this->assertSame(401, $this->api('GET', '/placement-campaigns')->getStatusCode());
    }

    #[Test]
    public function an_ordinary_member_cannot_read_the_campaign_list(): void
    {
        // What is being kept from them is not the advert, which they can see
        // on the page anyway — it is the rate, the advertiser and the caps.
        $this->assertSame(403, $this->api('GET', '/placement-campaigns', ['authenticatedAs' => 2])->getStatusCode());
    }

    #[Test]
    public function an_administrator_can_read_the_campaign_list(): void
    {
        $response = $this->api('GET', '/placement-campaigns', ['authenticatedAs' => 1]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Acme', $this->json($response)['data'][0]['attributes']['name']);
    }

    #[Test]
    public function a_member_granted_the_manage_permission_can_too(): void
    {
        // The real second case: a forum with two or three staff who handle
        // sponsors, served without a second auth model.
        $this->database()->table('group_permission')->insert([
            'group_id' => Group::MEMBER_ID,
            'permission' => Permissions::MANAGE,
        ]);

        $this->assertSame(200, $this->api('GET', '/placement-campaigns', ['authenticatedAs' => 2])->getStatusCode());
    }

    #[Test]
    public function a_campaign_reports_whether_it_is_actually_live(): void
    {
        // Computed, never read from `status`: a campaign whose end date has
        // passed is not live however active the row says it is.
        $this->database()->table('placement_campaigns')->update(['ends_at' => '2020-01-01 00:00:00']);

        $response = $this->api('GET', '/placement-campaigns', ['authenticatedAs' => 1]);

        $this->assertSame('active', $this->json($response)['data'][0]['attributes']['status']);
        $this->assertFalse($this->json($response)['data'][0]['attributes']['isLive']);
    }

    #[Test]
    public function counters_cannot_be_written_over_the_api(): void
    {
        // Otherwise the caps would be defeatable with one request.
        $this->api('PATCH', '/placement-campaigns/1', [
            'authenticatedAs' => 1,
            'json' => ['data' => ['attributes' => ['impressions' => 999999]]],
        ]);

        $this->assertSame(0, (int) $this->database()->table('placement_campaigns')->where('id', 1)->value('impressions'));
    }

    #[Test]
    public function targeting_rules_are_saved_with_the_campaign(): void
    {
        $this->api('PATCH', '/placement-campaigns/1', [
            'authenticatedAs' => 1,
            'json' => ['data' => ['attributes' => ['rules' => [
                ['dimension' => 'visitor', 'operator' => 'is', 'value' => 'member'],
                ['dimension' => 'route', 'operator' => 'is_not', 'value' => 'settings'],
            ]]]],
        ]);

        $rules = $this->database()->table('placement_campaign_rules')->where('campaign_id', 1)->get();

        $this->assertCount(2, $rules);
    }

    #[Test]
    public function a_malformed_rule_is_dropped_rather_than_failing_the_save(): void
    {
        // The alternative is a campaign that cannot be saved at all because of
        // one stale row in a form, and a half-written rule reaching the
        // evaluator would match nothing and be much harder to notice.
        $this->api('PATCH', '/placement-campaigns/1', [
            'authenticatedAs' => 1,
            'json' => ['data' => ['attributes' => ['rules' => [
                ['dimension' => 'visitor', 'operator' => 'is', 'value' => 'member'],
                ['dimension' => 'visitor', 'operator' => 'sideways', 'value' => 'member'],
                ['dimension' => '', 'operator' => 'is', 'value' => 'x'],
                ['dimension' => 'route', 'operator' => 'is', 'value' => ''],
            ]]]],
        ]);

        $rules = $this->database()->table('placement_campaign_rules')->where('campaign_id', 1)->get();

        $this->assertCount(1, $rules);
        $this->assertSame('visitor', $rules[0]->dimension);
    }

    #[Test]
    public function saving_rules_replaces_them_rather_than_appending(): void
    {
        foreach ([1, 2] as $_) {
            $this->api('PATCH', '/placement-campaigns/1', [
                'authenticatedAs' => 1,
                'json' => ['data' => ['attributes' => ['rules' => [
                    ['dimension' => 'visitor', 'operator' => 'is', 'value' => 'member'],
                ]]]],
            ]);
        }

        $this->assertSame(1, $this->database()->table('placement_campaign_rules')->where('campaign_id', 1)->count());
    }

    #[Test]
    public function slot_assignments_are_saved_with_the_creative(): void
    {
        $this->api('PATCH', '/placement-creatives/1', [
            'authenticatedAs' => 1,
            'json' => ['data' => ['attributes' => ['placements' => [
                'index_above_list' => 90,
                'discussion_sidebar' => null,
            ]]]],
        ]);

        $rows = $this->database()->table('placement_assignments')->where('creative_id', 1)->orderBy('placement_key')->get();

        $this->assertCount(2, $rows);
        $this->assertSame('discussion_sidebar', $rows[0]->placement_key);
        $this->assertNull($rows[0]->weight);
        $this->assertSame(90, (int) $rows[1]->weight);
    }

    #[Test]
    public function an_assignment_to_a_slot_that_does_not_exist_is_dropped(): void
    {
        // A key nothing renders would be an assignment that silently never
        // shows, indistinguishable from a targeting problem.
        $this->api('PATCH', '/placement-creatives/1', [
            'authenticatedAs' => 1,
            'json' => ['data' => ['attributes' => ['placements' => [
                'index_above_list' => null,
                'acme.nowhere' => null,
            ]]]],
        ]);

        $keys = $this->database()->table('placement_assignments')->where('creative_id', 1)->pluck('placement_key');

        $this->assertSame(['index_above_list'], $keys->all());
    }

    #[Test]
    public function editing_an_approved_creative_sends_it_back_for_review(): void
    {
        // Without this, "approve once, edit freely" is a way to get anything
        // at all onto every page of the forum.
        $this->api('PATCH', '/placement-creatives/1', [
            'authenticatedAs' => 1,
            'json' => ['data' => ['attributes' => ['payload' => ['asset' => 'https://example.com/b.png']]]],
        ]);

        $this->assertSame('pending', $this->database()->table('placement_creatives')->where('id', 1)->value('status'));
    }

    #[Test]
    public function renaming_a_creative_does_not_send_it_back_for_review(): void
    {
        // Only a change to what is actually shown does.
        $this->api('PATCH', '/placement-creatives/1', [
            'authenticatedAs' => 1,
            'json' => ['data' => ['attributes' => ['name' => 'Acme leaderboard v2']]],
        ]);

        $this->assertSame('approved', $this->database()->table('placement_creatives')->where('id', 1)->value('status'));
    }

    #[Test]
    public function a_creative_cannot_point_somewhere_a_browser_would_execute(): void
    {
        $response = $this->api('PATCH', '/placement-creatives/1', [
            'authenticatedAs' => 1,
            'json' => ['data' => ['attributes' => ['destinationUrl' => 'javascript:alert(1)']]],
        ]);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame(
            'https://example.com/offer',
            $this->database()->table('placement_creatives')->where('id', 1)->value('destination_url')
        );
    }

    #[Test]
    public function an_advertisers_report_token_never_leaves_over_the_api(): void
    {
        // It is the one credential here that reaches somebody outside the
        // forum, so it appears exactly once: in the response to the request
        // that created it.
        $this->database()->table('placement_advertisers')->insert([
            'id' => 1,
            'name' => 'Acme',
            'report_token' => 'secret-token-value',
        ]);

        $body = (string) $this->api('GET', '/placement-advertisers', ['authenticatedAs' => 1])->getBody();

        $this->assertStringNotContainsString('secret-token-value', $body);
        $this->assertTrue($this->json($this->api('GET', '/placement-advertisers', ['authenticatedAs' => 1]))['data'][0]['attributes']['hasReportToken']);
    }

    #[Test]
    public function the_admin_client_is_told_which_slots_exist(): void
    {
        // Placements are declared in code, so there is nothing to list over a
        // CRUD endpoint. They travel on the forum resource instead.
        $forum = $this->json($this->api('GET', '/', ['authenticatedAs' => 1]))['data']['attributes'];

        $this->assertTrue($forum['canManagePlacements']);
        $this->assertContains('index_above_list', array_column($forum['placementSlots'], 'key'));
        $this->assertContains('visitor', array_column($forum['placementDimensions'], 'key'));
    }

    #[Test]
    public function an_ordinary_member_is_not_told_which_slots_exist(): void
    {
        $forum = $this->json($this->api('GET', '/', ['authenticatedAs' => 2]))['data']['attributes'];

        $this->assertFalse($forum['canManagePlacements']);
        $this->assertArrayNotHasKey('placementSlots', $forum);
        $this->assertArrayNotHasKey('placementDimensions', $forum);
    }

    #[Test]
    public function a_slot_is_configured_by_its_key_without_a_row_existing_first(): void
    {
        // Settings are lazy, so the first edit of a slot has nothing to update.
        // Making the client create the row instead would mean also stopping it
        // creating rows for keys that render nothing.
        $response = $this->api('PATCH', '/placement-settings/index_above_list', [
            'authenticatedAs' => 1,
            'json' => ['data' => ['attributes' => ['enabled' => false, 'maxFill' => 2]]],
        ]);

        $this->assertSame(200, $response->getStatusCode());

        $row = $this->database()->table('placement_settings')->where('key', 'index_above_list')->first();

        $this->assertNotNull($row);
        $this->assertEquals(0, $row->enabled);
        $this->assertSame(2, (int) $row->max_fill);
    }

    #[Test]
    public function a_slot_that_nothing_renders_cannot_be_configured(): void
    {
        // Otherwise an administrator could create settings for a key that never
        // appears, and then wonder why the advert never shows.
        $response = $this->api('PATCH', '/placement-settings/acme.nowhere', [
            'authenticatedAs' => 1,
            'json' => ['data' => ['attributes' => ['enabled' => false]]],
        ]);

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame(0, $this->database()->table('placement_settings')->count());
    }

    #[Test]
    public function an_ordinary_member_cannot_configure_a_slot(): void
    {
        $response = $this->api('PATCH', '/placement-settings/index_above_list', [
            'authenticatedAs' => 2,
            'json' => ['data' => ['attributes' => ['enabled' => false]]],
        ]);

        $this->assertSame(403, $response->getStatusCode());
    }

    #[Test]
    public function a_creative_type_nobody_registered_is_refused(): void
    {
        $response = $this->api('PATCH', '/placement-creatives/1', [
            'authenticatedAs' => 1,
            'json' => ['data' => ['attributes' => ['type' => 'invented']]],
        ]);

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('image', $this->database()->table('placement_creatives')->where('id', 1)->value('type'));
    }

    #[Test]
    public function a_payload_that_does_not_match_its_type_is_refused(): void
    {
        // Without this the column accepts anything at all, and the renderer is
        // the first thing to find out.
        $response = $this->api('PATCH', '/placement-creatives/1', [
            'authenticatedAs' => 1,
            'json' => ['data' => ['attributes' => ['payload' => ['asset' => 'javascript:alert(1)']]]],
        ]);

        $this->assertSame(422, $response->getStatusCode());
    }

    #[Test]
    public function a_payload_is_stored_as_its_type_says_rather_than_as_it_arrived(): void
    {
        $this->api('PATCH', '/placement-creatives/1', [
            'authenticatedAs' => 1,
            'json' => ['data' => ['attributes' => ['payload' => [
                'asset' => '  https://example.com/b.png  ',
                'alt' => '',
                'width' => '728',
                'nonsense' => 'dropped',
            ]]]],
        ]);

        $payload = json_decode((string) $this->database()->table('placement_creatives')->where('id', 1)->value('payload'), true);

        $this->assertSame('https://example.com/b.png', $payload['asset'], 'trimmed');
        $this->assertSame(728, $payload['width'], 'stored as a number');
        $this->assertArrayNotHasKey('alt', $payload, 'empty values are dropped, not stored as ""');
        $this->assertArrayNotHasKey('nonsense', $payload, 'unknown keys are not kept');
    }

    #[Test]
    public function raw_html_is_refused_while_the_config_flag_is_off(): void
    {
        // Two keys have to be turned at once: this permission, and a flag in
        // config.php that no web request can set.
        $this->database()->table('group_permission')->insert([
            'group_id' => 1,
            'permission' => Permissions::AUTHOR_HTML,
        ]);

        $response = $this->api('PATCH', '/placement-creatives/1', [
            'authenticatedAs' => 1,
            'json' => ['data' => ['attributes' => [
                'type' => 'raw_html',
                'payload' => ['html' => '<b>hello</b>', 'height' => 250],
            ]]],
        ]);

        $this->assertSame(400, $response->getStatusCode());
        $this->assertStringContainsString('config.php', (string) $response->getBody());
    }

    #[Test]
    public function ads_txt_is_a_404_until_somebody_authorises_a_seller(): void
    {
        // The specification's own reading: an empty file means "nobody is
        // authorised", which would stop every network buying the inventory.
        $this->assertSame(404, $this->send($this->request('GET', '/ads.txt'))->getStatusCode());
    }

    #[Test]
    public function ads_txt_is_served_as_plain_text_once_it_has_contents(): void
    {
        $this->setting('datlechin-placements.ads_txt', "google.com, pub-1, DIRECT\r\nexample.com, 2, RESELLER");

        $response = $this->send($this->request('GET', '/ads.txt'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringStartsWith('text/plain', $response->getHeaderLine('Content-Type'));

        // Line-oriented format: a file pasted from Windows would otherwise
        // carry a stray carriage return on every record.
        $this->assertSame("google.com, pub-1, DIRECT\nexample.com, 2, RESELLER\n", (string) $response->getBody());
    }

    #[Test]
    public function ads_txt_is_readable_by_anybody(): void
    {
        // It exists to be crawled. Requiring a session would defeat it.
        $this->setting('datlechin-placements.ads_txt', 'google.com, pub-1, DIRECT');

        $this->assertSame(200, $this->send($this->request('GET', '/ads.txt'))->getStatusCode());
    }

    #[Test]
    public function an_advertiser_report_link_is_issued_once_and_never_again(): void
    {
        // The URL is the credential. Listing advertisers later must not be a
        // way to recover it.
        $this->database()->table('placement_advertisers')->insert(['id' => 1, 'name' => 'Acme']);

        $created = $this->json($this->api('PATCH', '/placement-advertisers/1', [
            'authenticatedAs' => 1,
            'json' => ['data' => ['attributes' => ['regenerateReportToken' => true]]],
        ]));

        $url = $created['data']['attributes']['reportUrl'] ?? null;

        $this->assertNotNull($url);
        $this->assertStringContainsString('/r/', $url);

        $listed = $this->json($this->api('GET', '/placement-advertisers', ['authenticatedAs' => 1]));

        $this->assertNull($listed['data'][0]['attributes']['reportUrl'] ?? null);
        $this->assertTrue($listed['data'][0]['attributes']['hasReportToken']);
    }

    #[Test]
    public function the_report_link_shows_only_that_advertisers_delivery(): void
    {
        $this->database()->table('placement_advertisers')->insert(['id' => 1, 'name' => 'Acme']);
        $this->database()->table('placement_campaigns')->where('id', 1)->update(['advertiser_id' => 1]);
        $this->database()->table('placement_stats')->insert([
            'bucket_start' => '2026-08-28 10:00:00',
            'campaign_id' => 1,
            'creative_id' => 1,
            'placement_key' => 'index_above_list',
            'device' => 'desktop',
            'impressions' => 5000,
            'viewable_impressions' => 3000,
            'clicks' => 50,
        ]);

        $token = $this->json($this->api('PATCH', '/placement-advertisers/1', [
            'authenticatedAs' => 1,
            'json' => ['data' => ['attributes' => ['regenerateReportToken' => true]]],
        ]))['data']['attributes']['reportUrl'];

        $path = parse_url($token, PHP_URL_PATH);
        $response = $this->send($this->request('GET', $path));
        $body = (string) $response->getBody();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Acme', $body);
        $this->assertStringContainsString('5,000', $body);
        $this->assertStringContainsString('60.0%', $body, 'viewable rate');

        // The URL is the credential, so it must not end up in an index.
        $this->assertStringContainsString('noindex', $response->getHeaderLine('X-Robots-Tag'));

        // Never targeting, never rates, never another advertiser.
        $this->assertStringNotContainsString('rate_amount', $body);
        $this->assertStringNotContainsString('visitor', $body);
    }

    #[Test]
    public function a_revoked_report_link_stops_working(): void
    {
        $this->database()->table('placement_advertisers')->insert(['id' => 1, 'name' => 'Acme']);

        $url = $this->json($this->api('PATCH', '/placement-advertisers/1', [
            'authenticatedAs' => 1,
            'json' => ['data' => ['attributes' => ['regenerateReportToken' => true]]],
        ]))['data']['attributes']['reportUrl'];

        $path = parse_url($url, PHP_URL_PATH);
        $this->assertSame(200, $this->send($this->request('GET', $path))->getStatusCode());

        $this->api('PATCH', '/placement-advertisers/1', [
            'authenticatedAs' => 1,
            'json' => ['data' => ['attributes' => ['regenerateReportToken' => false]]],
        ]);

        $this->assertSame(404, $this->send($this->request('GET', $path))->getStatusCode());
    }

    #[Test]
    public function a_made_up_report_link_is_not_found_rather_than_forbidden(): void
    {
        // The difference would tell somebody guessing that they got the length
        // right.
        $this->assertSame(404, $this->send($this->request('GET', '/r/not-a-real-token'))->getStatusCode());
    }

    #[Test]
    public function an_expired_report_link_stops_working(): void
    {
        // Revoking nulls the token, so it stops working for a duller reason.
        // Expiry is the case that needs the check.
        $this->database()->table('placement_advertisers')->insert([
            'id' => 1,
            'name' => 'Acme',
            'report_token' => 'a-token-that-has-expired',
            'report_token_expires_at' => '2020-01-01 00:00:00',
        ]);

        $this->assertSame(404, $this->send($this->request('GET', '/r/a-token-that-has-expired'))->getStatusCode());
    }

    #[Test]
    public function a_report_link_still_works_before_it_expires(): void
    {
        $this->database()->table('placement_advertisers')->insert([
            'id' => 1,
            'name' => 'Acme',
            'report_token' => 'a-token-that-is-still-good',
            'report_token_expires_at' => '2099-01-01 00:00:00',
        ]);

        $this->assertSame(200, $this->send($this->request('GET', '/r/a-token-that-is-still-good'))->getStatusCode());
    }

    #[Test]
    public function one_advertiser_never_sees_anothers_delivery(): void
    {
        $this->database()->table('placement_advertisers')->insert([
            ['id' => 1, 'name' => 'Acme', 'report_token' => 'acme-token'],
            ['id' => 2, 'name' => 'Rival', 'report_token' => 'rival-token'],
        ]);

        $this->database()->table('placement_campaigns')->where('id', 1)->update(['advertiser_id' => 1]);
        $this->database()->table('placement_campaigns')->insert([
            'id' => 2,
            'advertiser_id' => 2,
            'name' => 'Rival campaign',
            'status' => 'active',
            'tier' => 50,
            'is_house' => false,
            'pacing' => 'asap',
            'frequency_window' => 'day',
            'impressions' => 0,
            'clicks' => 0,
        ]);

        $this->database()->table('placement_stats')->insert([
            [
                'bucket_start' => '2026-08-28 10:00:00', 'campaign_id' => 1, 'creative_id' => 1,
                'placement_key' => 'index_above_list', 'device' => 'desktop',
                'impressions' => 1111, 'viewable_impressions' => 0, 'clicks' => 0,
            ],
            [
                'bucket_start' => '2026-08-28 10:00:00', 'campaign_id' => 2, 'creative_id' => 2,
                'placement_key' => 'index_above_list', 'device' => 'desktop',
                'impressions' => 9999, 'viewable_impressions' => 0, 'clicks' => 0,
            ],
        ]);

        $body = (string) $this->send($this->request('GET', '/r/acme-token'))->getBody();

        $this->assertStringContainsString('1,111', $body);
        $this->assertStringNotContainsString('9,999', $body, "one advertiser saw another's delivery");
        $this->assertStringNotContainsString('Rival', $body);
    }

    #[Test]
    public function approving_a_creative_records_who_decided_and_when(): void
    {
        // A queue of approved creatives that says nothing about who let each
        // one onto the forum is exactly what somebody asks about after the one
        // that should not have been.
        $this->database()->table('placement_creatives')->where('id', 1)->update(['status' => 'pending']);

        $this->api('PATCH', '/placement-creatives/1', [
            'authenticatedAs' => 1,
            'json' => ['data' => ['attributes' => ['status' => 'approved']]],
        ]);

        $row = $this->database()->table('placement_creatives')->where('id', 1)->first();

        $this->assertSame('approved', $row->status);
        $this->assertSame(1, (int) $row->reviewed_by);
        $this->assertNotNull($row->reviewed_at);
    }

    #[Test]
    public function rejecting_a_creative_keeps_the_reason(): void
    {
        $this->api('PATCH', '/placement-creatives/1', [
            'authenticatedAs' => 1,
            'json' => ['data' => ['attributes' => [
                'status' => 'rejected',
                'reviewReason' => 'The landing page asks for a password.',
            ]]],
        ]);

        $row = $this->database()->table('placement_creatives')->where('id', 1)->first();

        $this->assertSame('rejected', $row->status);
        $this->assertSame('The landing page asks for a password.', $row->review_reason);
    }

    #[Test]
    public function approving_clears_a_reason_left_over_from_a_rejection(): void
    {
        // Otherwise a stale explanation stays attached to something that was
        // accepted.
        $this->api('PATCH', '/placement-creatives/1', [
            'authenticatedAs' => 1,
            'json' => ['data' => ['attributes' => ['status' => 'rejected', 'reviewReason' => 'No.']]],
        ]);

        $this->api('PATCH', '/placement-creatives/1', [
            'authenticatedAs' => 1,
            'json' => ['data' => ['attributes' => ['status' => 'approved']]],
        ]);

        $this->assertNull($this->database()->table('placement_creatives')->where('id', 1)->value('review_reason'));
    }

    #[Test]
    public function sending_a_creative_back_to_pending_clears_the_decision(): void
    {
        // From pending, so approving is a real change of status: setting an
        // already-approved creative to approved is not dirty and records
        // nothing, which would make this test pass without testing anything.
        $this->database()->table('placement_creatives')->where('id', 1)->update(['status' => 'pending']);

        $this->api('PATCH', '/placement-creatives/1', [
            'authenticatedAs' => 1,
            'json' => ['data' => ['attributes' => ['status' => 'approved']]],
        ]);

        $this->assertSame(1, (int) $this->database()->table('placement_creatives')->where('id', 1)->value('reviewed_by'));

        $this->api('PATCH', '/placement-creatives/1', [
            'authenticatedAs' => 1,
            'json' => ['data' => ['attributes' => ['status' => 'pending']]],
        ]);

        $row = $this->database()->table('placement_creatives')->where('id', 1)->first();

        $this->assertNull($row->reviewed_by);
        $this->assertNull($row->reviewed_at);
    }

    #[Test]
    public function editing_what_is_shown_clears_the_decision_too(): void
    {
        // Editing an approved creative sends it back to pending, and a pending
        // creative must not still claim it was reviewed.
        $this->api('PATCH', '/placement-creatives/1', [
            'authenticatedAs' => 1,
            'json' => ['data' => ['attributes' => ['payload' => ['asset' => 'https://example.com/c.png']]]],
        ]);

        $row = $this->database()->table('placement_creatives')->where('id', 1)->first();

        $this->assertSame('pending', $row->status);
        $this->assertNull($row->reviewed_by);
    }
}
