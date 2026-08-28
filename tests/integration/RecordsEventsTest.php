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
use Datlechin\Placements\Model\Stat;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * The measurement round trip, end to end.
 *
 * Unit tests cover the token and the buffer separately; what only a real
 * request can show is that the token the page hands out is the one the beacon
 * endpoint accepts, and that a forged one is not.
 */
class RecordsEventsTest extends TestCase
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
     * The candidate the forum page was actually served, token and all.
     *
     * @return array<string, mixed>
     */
    protected function served(): array
    {
        $response = $this->send($this->request('GET', '/')->withHeader('User-Agent', 'Mozilla/5.0'));

        preg_match('/<script id="flarum-json-payload" type="application\/json">(.*?)<\/script>/s', (string) $response->getBody(), $matches);

        $payload = json_decode(html_entity_decode($matches[1] ?? '', ENT_QUOTES), true) ?? [];

        return $payload['placement']['slots']['index_above_list']['candidates'][0] ?? [];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function report(array $candidate, string $type = Stat::IMPRESSION, array $overrides = []): int
    {
        $response = $this->send($this->request('POST', '/api/placements/events', [
            'json' => ['events' => [array_merge([
                'type' => $type,
                'token' => $candidate['token'] ?? '',
                'nonce' => $candidate['nonce'] ?? '',
                'issued' => $candidate['issued'] ?? 0,
                'creative' => $candidate['creative'] ?? 0,
                'campaign' => $candidate['campaign'] ?? 0,
                'placement' => 'index_above_list',
                'device' => 'desktop',
            ], $overrides)]],
        ]));

        // The buffer only reaches the database opportunistically, so a test
        // flushes it rather than firing requests until chance obliges.
        $this->app()->getContainer()->make(Recorder::class)->flush();

        return $response->getStatusCode();
    }

    /**
     * @return array<string, int>
     */
    protected function counts(): array
    {
        $row = $this->database()->table('placement_stats')->first();

        return $row === null
            ? ['impressions' => 0, 'clicks' => 0]
            : ['impressions' => (int) $row->impressions, 'clicks' => (int) $row->clicks];
    }

    #[Test]
    public function the_page_hands_out_a_token_with_every_candidate(): void
    {
        $candidate = $this->served();

        $this->assertNotEmpty($candidate['token'] ?? null);
        $this->assertNotEmpty($candidate['nonce'] ?? null);
        $this->assertIsInt($candidate['issued'] ?? null);
    }

    #[Test]
    public function a_reported_impression_is_counted(): void
    {
        $this->assertSame(204, $this->report($this->served()));

        $this->assertSame(1, $this->counts()['impressions']);
    }

    #[Test]
    public function a_click_is_counted_on_the_same_token_as_its_impression(): void
    {
        // The click has to prove the impression was served, so they share a
        // token — but counting one must not prevent counting the other.
        $candidate = $this->served();

        $this->report($candidate, Stat::IMPRESSION);
        $this->report($candidate, Stat::CLICK);

        $this->assertSame(['impressions' => 1, 'clicks' => 1], $this->counts());
    }

    #[Test]
    public function replaying_the_same_event_counts_once(): void
    {
        $candidate = $this->served();

        $this->report($candidate);
        $this->report($candidate);
        $this->report($candidate);

        $this->assertSame(1, $this->counts()['impressions']);
    }

    #[Test]
    public function a_forged_token_counts_nothing(): void
    {
        $this->report($this->served(), Stat::IMPRESSION, ['token' => 'not-a-real-token']);

        $this->assertSame(0, $this->counts()['impressions']);
    }

    #[Test]
    public function a_token_cannot_be_moved_to_another_creative(): void
    {
        // The attack the signature exists for: report impressions against a
        // rival's creative until their cap is exhausted and their campaign is
        // pulled off the forum.
        $this->report($this->served(), Stat::IMPRESSION, ['creative' => 999]);

        $this->assertSame(0, $this->counts()['impressions']);
    }

    #[Test]
    public function a_token_cannot_be_moved_to_another_slot(): void
    {
        $this->report($this->served(), Stat::IMPRESSION, ['placement' => 'discussion_sidebar']);

        $this->assertSame(0, $this->counts()['impressions']);
    }

    #[Test]
    public function a_client_cannot_claim_its_own_traffic_was_invalid(): void
    {
        // `filtered` is a verdict this server reaches, never a claim a client
        // may make about itself.
        $this->report($this->served(), Stat::FILTERED);

        $this->assertSame(0, (int) ($this->database()->table('placement_stats')->value('filtered') ?? 0));
    }

    #[Test]
    public function an_event_type_nobody_declared_counts_nothing(): void
    {
        $this->report($this->served(), 'something_invented');

        $this->assertSame(0, $this->database()->table('placement_stats')->count());
    }

    #[Test]
    public function a_counted_impression_moves_the_running_totals_the_caps_are_checked_against(): void
    {
        $this->report($this->served());

        $this->assertSame(1, (int) $this->database()->table('placement_campaigns')->where('id', 1)->value('impressions'));
        $this->assertSame(1, (int) $this->database()->table('placement_creatives')->where('id', 1)->value('impressions'));
    }

    #[Test]
    public function a_malformed_body_is_answered_calmly(): void
    {
        // A beacon fires from a page that may be closing. Nothing is listening
        // for a reply, and an error page would be wasted on it.
        $response = $this->send($this->request('POST', '/api/placements/events', [
            'json' => ['events' => 'not an array'],
        ]));

        $this->assertSame(204, $response->getStatusCode());
    }

    #[Test]
    public function the_report_counts_what_was_reported(): void
    {
        $this->report($this->served());

        $body = json_decode((string) $this->send(
            $this->request('GET', '/api/placements/report', ['authenticatedAs' => 1])
        )->getBody(), true);

        $this->assertSame(1, $body['totals']['impressions']);
        $this->assertSame('index_above_list', $body['placements'][0]['key']);
        $this->assertSame(1, $body['placements'][0]['impressions']);
        $this->assertCount(1, $body['daily']);
    }

    #[Test]
    public function the_report_flushes_before_answering(): void
    {
        // An administrator opening the reports a minute after a campaign went
        // live should not be told it delivered nothing.
        $candidate = $this->served();

        $this->send($this->request('POST', '/api/placements/events', [
            'json' => ['events' => [[
                'type' => 'impression',
                'token' => $candidate['token'],
                'nonce' => $candidate['nonce'],
                'issued' => $candidate['issued'],
                'creative' => $candidate['creative'],
                'campaign' => $candidate['campaign'],
                'placement' => 'index_above_list',
                'device' => 'desktop',
            ]]],
        ]));

        $body = json_decode((string) $this->send(
            $this->request('GET', '/api/placements/report', ['authenticatedAs' => 1])
        )->getBody(), true);

        $this->assertSame(1, $body['totals']['impressions']);
    }

    #[Test]
    public function an_ordinary_member_cannot_read_the_report(): void
    {
        $response = $this->send($this->request('GET', '/api/placements/report', ['authenticatedAs' => 2]));

        $this->assertSame(403, $response->getStatusCode());
    }

    #[Test]
    public function rejected_traffic_is_shown_rather_than_hidden(): void
    {
        $body = json_decode((string) $this->send(
            $this->request('GET', '/api/placements/report', ['authenticatedAs' => 1])
        )->getBody(), true);

        $this->assertArrayHasKey('filtered', $body['totals']);
    }

    #[Test]
    public function whoever_created_a_campaign_is_told_when_it_stops(): void
    {
        // A campaign that has quietly reached its cap looks exactly like one
        // that is running: the row still says "active", and the only symptom
        // is that an advertiser's numbers stop moving.
        $this->database()->table('placement_campaigns')->where('id', 1)->update([
            'max_impressions' => 1,
            'created_by' => 1,
        ]);

        $this->report($this->served());

        $notification = $this->database()->table('notifications')->first();

        $this->assertNotNull($notification, 'nobody was told the campaign stopped');
        $this->assertSame('datlechinPlacementCampaignStopped', $notification->type);
        $this->assertSame(1, (int) $notification->user_id);
        $this->assertStringContainsString('impressions', (string) $notification->data);
    }

    #[Test]
    public function nobody_is_told_while_a_campaign_is_still_running(): void
    {
        $this->database()->table('placement_campaigns')->where('id', 1)->update([
            'max_impressions' => 100,
            'created_by' => 1,
        ]);

        $this->report($this->served());

        $this->assertSame(0, $this->database()->table('notifications')->count());
    }

    #[Test]
    public function a_campaign_with_no_cap_never_generates_the_extra_read(): void
    {
        // Most campaigns have no cap, and none of them should cost a lookup
        // on every flush.
        $this->database()->table('placement_campaigns')->where('id', 1)->update(['created_by' => 1]);

        $this->report($this->served());

        $this->assertSame(0, $this->database()->table('notifications')->count());
    }

    #[Test]
    public function the_report_can_be_downloaded_as_a_spreadsheet(): void
    {
        // What somebody reconciling an invoice actually wants.
        $this->report($this->served());

        // Set explicitly: the test request builder puts the path on the URI
        // but does not parse a query string into `getQueryParams()`.
        $response = $this->send(
            $this->request('GET', '/api/placements/report', ['authenticatedAs' => 1])
                ->withQueryParams(['format' => 'csv'])
        );

        $this->assertStringStartsWith('text/csv', $response->getHeaderLine('Content-Type'));
        $this->assertStringContainsString('attachment', $response->getHeaderLine('Content-Disposition'));
        $this->assertStringStartsWith('section,key,name,impressions,viewable,clicks', (string) $response->getBody());
    }
}
