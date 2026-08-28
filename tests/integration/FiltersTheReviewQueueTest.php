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

use Datlechin\Placements\Model\Creative;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;

/**
 * `filter[status]` on the creative list, which is what the review queue is.
 *
 * Worth an integration test rather than a unit one: the filter only works if a
 * searcher is registered for the model, because Flarum routes list filters
 * through a searcher and refuses a resource that declares its own. A unit test
 * on the filter class would pass whether or not that wiring exists.
 */
class FiltersTheReviewQueueTest extends TestCase
{
    use RetrievesAuthorizedUsers;
    use SeedsCampaigns;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('datlechin-placements');

        $seed = $this->campaignSeed();

        // One of each of the statuses a queue distinguishes, on top of the
        // approved one the shared seed already provides.
        $seed['placement_creatives'][] = $this->creative(2, 'Pending banner', Creative::STATUS_PENDING);
        $seed['placement_creatives'][] = $this->creative(3, 'Rejected banner', Creative::STATUS_REJECTED);
        $seed['placement_creatives'][] = $this->creative(4, 'Draft banner', Creative::STATUS_DRAFT);

        $this->prepareDatabase([
            'users' => [$this->normalUser()],
            'group_permission' => [],
            ...$seed,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function creative(int $id, string $name, string $status): array
    {
        return [
            'id' => $id,
            'campaign_id' => 1,
            'name' => $name,
            'type' => 'image',
            'status' => $status,
            'weight' => 10,
            'destination_url' => 'https://example.com/offer',
            'payload' => json_encode(['asset' => 'https://example.com/a.png']),
            'impressions' => 0,
            'viewable_impressions' => 0,
            'clicks' => 0,
        ];
    }

    /**
     * The filter goes in as query *parameters*, not as a query string.
     * Flarum's test helper builds its ServerRequest from the path alone and
     * never parses one, so a `?filter[status]=pending` written into the path
     * arrives as no filter at all -- and the endpoint answers 200 with
     * everything, which looks exactly like a filter that does not work.
     *
     * @param  array<string, mixed>  $query
     */
    private function api(array $query = [], int $as = 1): ResponseInterface
    {
        return $this->send(
            $this->request('GET', '/api/placement-creatives', ['authenticatedAs' => $as])->withQueryParams($query)
        );
    }

    /**
     * @return list<string>
     */
    private function names(ResponseInterface $response): array
    {
        $body = json_decode((string) $response->getBody(), true);

        $names = array_map(fn (array $row): string => $row['attributes']['name'], $body['data'] ?? []);

        sort($names);

        return $names;
    }

    #[Test]
    public function the_list_is_unfiltered_without_one(): void
    {
        $response = $this->api();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['Acme leaderboard', 'Draft banner', 'Pending banner', 'Rejected banner'], $this->names($response));
    }

    #[Test]
    public function it_returns_only_the_status_asked_for(): void
    {
        $this->assertSame(['Pending banner'], $this->names($this->api(['filter' => ['status' => 'pending']])));
        $this->assertSame(['Acme leaderboard'], $this->names($this->api(['filter' => ['status' => 'approved']])));
    }

    /**
     * A queue shows what is waiting alongside what was turned down and not yet
     * resubmitted, so it asks for both at once.
     */
    #[Test]
    public function it_returns_several_statuses_at_once(): void
    {
        $names = $this->names($this->api(['filter' => ['status' => ['pending', 'rejected']]]));

        $this->assertSame(['Pending banner', 'Rejected banner'], $names);
    }

    #[Test]
    public function it_returns_everything_but_the_status_asked_for_when_negated(): void
    {
        $names = $this->names($this->api(['filter' => ['-status' => 'approved']]));

        $this->assertSame(['Draft banner', 'Pending banner', 'Rejected banner'], $names);
    }

    /**
     * A status nobody uses matches no rows, which is the right answer and the
     * reason the filter does not check values against a list. Turning it into
     * a 400 would break a queue whose statuses an extension had added to.
     */
    #[Test]
    public function an_unknown_status_matches_nothing_rather_than_failing(): void
    {
        $response = $this->api(['filter' => ['status' => 'nonsense']]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([], $this->names($response));
    }

    /**
     * The filter is reached through the list endpoint, and that endpoint is
     * what authorises. The searcher itself has no visibility scope, so this is
     * the test that says the gate is still there.
     */
    #[Test]
    public function an_ordinary_member_cannot_filter_the_list_either(): void
    {
        $this->assertSame(403, $this->api(['filter' => ['status' => 'pending']], as: 2)->getStatusCode());
    }

    #[Test]
    public function a_guest_cannot_filter_the_list(): void
    {
        $response = $this->send(
            $this->request('GET', '/api/placement-creatives')->withQueryParams(['filter' => ['status' => 'pending']])
        );

        $this->assertSame(401, $response->getStatusCode());
    }
}
