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

use Datlechin\Placements\Measurement\EventToken;
use Datlechin\Placements\Model\Stat;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Exchanging a token for a fresh one.
 *
 * A plan is minted once per page load, so in a single-page application one
 * nonce had to cover a whole reading session -- and a nonce is refused twice
 * by the browser and again by the server. A reader who moved through sixty
 * pages was worth one impression per creative, and once the token passed its
 * lifetime every further event was dropped while the adverts kept appearing.
 *
 * The property that makes this safe is that nothing is re-decided: every value
 * comes out of the signature, never out of the request body.
 */
class RefreshesTokensTest extends TestCase
{
    use RetrievesAuthorizedUsers;
    use SeedsCampaigns;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('datlechin-placements');
        $this->prepareDatabase(['group_permission' => [], ...$this->campaignSeed()]);
    }

    /**
     * A candidate as the page payload hands it over, tokens and all.
     *
     * @return array<string, mixed>
     */
    private function served(): array
    {
        // A real user agent: a request that looks like a crawler is served
        // nothing at all, and the payload would have no `placement` key.
        $response = $this->send($this->request('GET', '/')->withHeader('User-Agent', 'Mozilla/5.0'));

        preg_match('/<script id="flarum-json-payload" type="application\/json">(.*?)<\/script>/s', (string) $response->getBody(), $matches);

        $payload = json_decode(html_entity_decode($matches[1] ?? '', ENT_QUOTES), true) ?? [];

        $candidate = $payload['placement']['slots']['index_above_list']['candidates'][0] ?? null;

        $this->assertIsArray($candidate, 'the page should have served a candidate to report on');

        return [
            'creative' => $candidate['creative'],
            'campaign' => $candidate['campaign'],
            'placement' => 'index_above_list',
            'token' => $candidate['token'],
            'nonce' => $candidate['nonce'],
            'issued' => $candidate['issued'],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $tokens
     * @return array<string, mixed>
     */
    private function refresh(array $tokens): array
    {
        $response = $this->send(
            $this->request('POST', '/api/placements/tokens', ['json' => ['tokens' => $tokens]])
        );

        $this->assertSame(200, $response->getStatusCode());

        return json_decode((string) $response->getBody(), true);
    }

    #[Test]
    public function a_token_this_server_issued_is_exchanged_for_a_new_one(): void
    {
        $held = $this->served();

        $fresh = $this->refresh([$held])['tokens'];

        $this->assertCount(1, $fresh);
        $this->assertNotSame($held['nonce'], $fresh[0]['nonce'], 'a fresh nonce is the whole point');
        $this->assertNotSame($held['token'], $fresh[0]['token']);

        // The advert is the same one; only the proof is new.
        $this->assertSame($held['creative'], $fresh[0]['creative']);
        $this->assertSame($held['campaign'], $fresh[0]['campaign']);
        $this->assertSame('index_above_list', $fresh[0]['placement']);
    }

    /**
     * The undercount this exists to fix, end to end: the same advert, seen on
     * two pages, counted twice.
     */
    #[Test]
    public function a_refreshed_token_counts_a_second_impression(): void
    {
        $held = $this->served();

        $this->report($held);

        $fresh = $this->refresh([$held])['tokens'][0];

        $this->report([
            'creative' => $fresh['creative'],
            'campaign' => $fresh['campaign'],
            'placement' => $fresh['placement'],
            'token' => $fresh['token'],
            'nonce' => $fresh['nonce'],
            'issued' => $fresh['issued'],
        ]);

        $this->assertSame(2, (int) $this->database()->table('placement_stats')->sum('impressions'));
    }

    /**
     * @param  array<string, mixed>  $held
     */
    private function report(array $held): void
    {
        $this->send(
            $this->request('POST', '/api/placements/events', [
                'json' => ['events' => [['type' => Stat::IMPRESSION, ...$held]]],
            ])
        );

        $this->app()->getContainer()->make(\Datlechin\Placements\Measurement\Recorder::class)->flush();
    }

    #[Test]
    public function a_forged_token_is_refused(): void
    {
        $held = $this->served();
        $held['token'] = 'not-a-real-token';

        $this->assertSame([], $this->refresh([$held])['tokens']);
    }

    /**
     * The property the whole design rests on. A browser may only renew what it
     * was given: the triple is read out of the signature, so claiming a
     * different creative invalidates it rather than moving the advert.
     */
    #[Test]
    public function a_token_cannot_be_talked_into_naming_another_creative(): void
    {
        $held = $this->served();
        $held['creative'] = 999;

        $this->assertSame([], $this->refresh([$held])['tokens']);
    }

    #[Test]
    public function a_token_cannot_be_moved_to_another_slot(): void
    {
        $held = $this->served();
        $held['placement'] = 'index_sidebar';

        $this->assertSame([], $this->refresh([$held])['tokens']);
    }

    /**
     * A reading session ends. Beyond the refresh window the browser is simply
     * asking about a page nobody is looking at any more.
     */
    #[Test]
    public function a_token_older_than_the_refresh_window_is_refused(): void
    {
        $held = $this->served();
        $held['issued'] -= EventToken::REFRESH_WINDOW + 60;

        $this->assertSame([], $this->refresh([$held])['tokens']);
    }

    #[Test]
    public function a_request_with_nothing_in_it_answers_with_nothing(): void
    {
        $this->assertSame([], $this->refresh([])['tokens']);
    }
}
