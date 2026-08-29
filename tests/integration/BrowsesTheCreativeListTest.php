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
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;

/**
 * The creative list, narrowed.
 *
 * `filter[campaign]` is what lets a campaign have a page of its own. Without
 * it the only way to show one campaign's creatives was to fetch every creative
 * on the forum and throw away the ones belonging to other campaigns in the
 * browser -- which also meant a forum with more than fifty creatives showed
 * the wrong ones, or none.
 *
 * `filter[type]` is the audit question: which creatives on this forum execute
 * script.
 *
 * @see FiltersTheReviewQueueTest for `filter[status]`, which is the queue.
 */
class BrowsesTheCreativeListTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('datlechin-placements');

        $this->prepareDatabase([
            'users' => [$this->normalUser()],
            'group_permission' => [],
            'placement_campaigns' => [
                $this->campaign(1, 'Summer Sale'),
                $this->campaign(2, 'Winter promo'),
            ],
            'placement_creatives' => [
                $this->creative(1, 1, 'Hero banner', 'image'),
                $this->creative(2, 1, 'Sidebar text', 'text'),
                $this->creative(3, 2, 'Winter banner', 'image'),
                $this->creative(4, 2, 'Partner embed', 'raw_html'),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function campaign(int $id, string $name): array
    {
        return [
            'id' => $id,
            'name' => $name,
            'status' => Campaign::STATUS_ACTIVE,
            'tier' => Campaign::TIER_STANDARD,
            'is_house' => false,
            'pacing' => Campaign::PACING_ASAP,
            'frequency_window' => Campaign::WINDOW_DAY,
            'impressions' => 0,
            'clicks' => 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function creative(int $id, int $campaign, string $name, string $type): array
    {
        return [
            'id' => $id,
            'campaign_id' => $campaign,
            'name' => $name,
            'type' => $type,
            'status' => Creative::STATUS_APPROVED,
            'weight' => 10,
            'destination_url' => 'https://example.com/offer',
            'payload' => json_encode(['asset' => 'https://example.com/a.png']),
            'impressions' => 0,
            'viewable_impressions' => 0,
            'clicks' => 0,
        ];
    }

    /**
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
    public function it_returns_only_one_campaigns_creatives(): void
    {
        $this->assertSame(['Hero banner', 'Sidebar text'], $this->names($this->api(['filter' => ['campaign' => 1]])));
        $this->assertSame(['Partner embed', 'Winter banner'], $this->names($this->api(['filter' => ['campaign' => 2]])));
    }

    /**
     * A campaign that has been deleted, or an id somebody typed, returns an
     * empty list rather than everything. Returning everything is the failure
     * that matters: it would put another campaign's creatives on this
     * campaign's page.
     */
    #[Test]
    public function an_unknown_campaign_returns_nothing_rather_than_everything(): void
    {
        $this->assertSame([], $this->names($this->api(['filter' => ['campaign' => 999]])));
    }

    #[Test]
    public function it_filters_by_type(): void
    {
        $this->assertSame(['Partner embed'], $this->names($this->api(['filter' => ['type' => 'raw_html']])));
        $this->assertSame(['Hero banner', 'Winter banner'], $this->names($this->api(['filter' => ['type' => 'image']])));
    }

    #[Test]
    public function it_searches_by_name(): void
    {
        $this->assertSame(['Hero banner', 'Winter banner'], $this->names($this->api(['filter' => ['q' => 'banner']])));
    }

    /**
     * The combination a campaign page asks for: this campaign's creatives, of
     * this type. If the search or a filter escaped its grouping, this would
     * return creatives belonging to the other campaign.
     */
    #[Test]
    public function a_campaign_filter_narrows_a_search_rather_than_widening_it(): void
    {
        $names = $this->names($this->api(['filter' => ['q' => 'banner', 'campaign' => 2]]));

        $this->assertSame(['Winter banner'], $names);
    }

    #[Test]
    public function an_ordinary_member_cannot_browse_the_list(): void
    {
        $this->assertSame(403, $this->api(['filter' => ['campaign' => 1]], as: 2)->getStatusCode());
    }
}
