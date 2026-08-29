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
use Datlechin\Placements\Search\CampaignSearcher;
use Datlechin\Placements\Search\Filter\SourceFilter;
use Datlechin\Placements\Search\Fulltext\CampaignTextFilter;
use Datlechin\Placements\Support\Permissions;
use Flarum\Search\Database\DatabaseSearchState;
use Flarum\Search\Filter\FilterManager;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;

/**
 * Searching, filtering, sorting and paging the campaign list.
 *
 * All of it is new. The admin list used to ask for every campaign at once and
 * draw whichever fifty the server sent, with nothing saying there were more, so
 * a forum that had sold more than fifty campaigns could not reach the rest at
 * all.
 *
 * Integration rather than unit throughout: none of this works unless a searcher
 * is registered for the model, because Flarum routes list filters and sorts
 * through one and `AbstractDatabaseResource::filters()` is final and throws. A
 * unit test on any of the filter classes would pass whether or not that wiring
 * exists.
 */
class BrowsesTheCampaignListTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('datlechin-placements');

        $this->prepareDatabase([
            'users' => [$this->normalUser()],
            'group_permission' => [],
            'placement_advertisers' => [
                ['id' => 1, 'name' => 'Acme Corp', 'user_id' => null],
                // What `MemberInventory` provisions when a member submits an
                // advert: an advertiser that is a forum account, named after
                // them.
                ['id' => 2, 'name' => 'Riley', 'user_id' => 2],
            ],
            // Every name starts with a capital on purpose. Databases disagree
            // about where a lowercase initial sorts -- MySQL's default
            // collation folds case, PostgreSQL's C collation puts every
            // uppercase letter first -- so a mixed-case fixture would make the
            // sort assertions below pass on some drivers in the matrix and fail
            // on others for reasons that have nothing to do with the sort.
            'placement_campaigns' => [
                $this->campaign(1, 'Summer Sale', Campaign::STATUS_ACTIVE, Campaign::TIER_STANDARD, 1, 100),
                $this->campaign(2, 'Winter promo', Campaign::STATUS_PAUSED, Campaign::TIER_GUARANTEED, 1, 50),
                $this->campaign(3, 'Riley', Campaign::STATUS_DRAFT, Campaign::TIER_REMNANT, 2, 0),
                // No advertiser at all, which is what a house campaign usually
                // looks like.
                $this->campaign(4, 'House filler', Campaign::STATUS_ACTIVE, Campaign::TIER_HOUSE, null, 5),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function campaign(int $id, string $name, string $status, int $tier, ?int $advertiser, int $impressions): array
    {
        return [
            'id' => $id,
            'name' => $name,
            'status' => $status,
            'tier' => $tier,
            'advertiser_id' => $advertiser,
            'is_house' => $tier === Campaign::TIER_HOUSE,
            'pacing' => Campaign::PACING_ASAP,
            'frequency_window' => Campaign::WINDOW_DAY,
            'impressions' => $impressions,
            'clicks' => 0,
        ];
    }

    /**
     * Query parameters, not a query string: Flarum's test helper builds its
     * ServerRequest from the path alone and never parses one, so anything
     * written after a `?` arrives as no filter at all -- and the endpoint then
     * answers 200 with everything, which looks exactly like a filter that does
     * not work.
     *
     * @param  array<string, mixed>  $query
     */
    private function api(array $query = [], int $as = 1): ResponseInterface
    {
        return $this->send(
            $this->request('GET', '/api/placement-campaigns', ['authenticatedAs' => $as])->withQueryParams($query)
        );
    }

    /**
     * Names in the order the server returned them, so that a sort can be
     * asserted rather than just its contents.
     *
     * @return list<string>
     */
    private function names(ResponseInterface $response): array
    {
        $body = json_decode((string) $response->getBody(), true);

        return array_map(fn (array $row): string => $row['attributes']['name'], $body['data'] ?? []);
    }

    /**
     * @return list<string>
     */
    private function sortedNames(ResponseInterface $response): array
    {
        $names = $this->names($response);
        sort($names);

        return $names;
    }

    #[Test]
    public function the_list_is_everything_without_a_filter(): void
    {
        $response = $this->api();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['House filler', 'Riley', 'Summer Sale', 'Winter promo'], $this->sortedNames($response));
    }

    #[Test]
    public function a_search_term_matches_part_of_a_name(): void
    {
        $this->assertSame(['Summer Sale'], $this->sortedNames($this->api(['filter' => ['q' => 'Sale']])));
    }

    /**
     * The cross-driver case. MySQL and MariaDB fold case because their default
     * collation does, PostgreSQL does not fold it at all and needs `ILIKE`, and
     * SQLite folds ASCII only. Written once and left to `LIKE`, this returns
     * nothing on PostgreSQL -- on the database hardest to notice it on.
     */
    #[Test]
    public function a_search_term_ignores_case_on_every_driver(): void
    {
        $this->assertSame(['Summer Sale'], $this->sortedNames($this->api(['filter' => ['q' => 'summer']])));
        $this->assertSame(['Summer Sale'], $this->sortedNames($this->api(['filter' => ['q' => 'SUMMER']])));
    }

    #[Test]
    public function a_search_term_combines_with_the_other_filters(): void
    {
        // "Winter promo" matches the term but is paused, so asking for active
        // campaigns matching "promo" is asking for nothing.
        $this->assertSame([], $this->names($this->api([
            'filter' => ['q' => 'promo', 'status' => Campaign::STATUS_ACTIVE],
        ])));

        $this->assertSame(['Winter promo'], $this->names($this->api([
            'filter' => ['q' => 'promo', 'status' => Campaign::STATUS_PAUSED],
        ])));
    }

    /**
     * The reason the search groups its alternatives instead of hanging a bare
     * `orWhere` on the query.
     *
     * Flarum applies the fulltext filter before every other filter, so the only
     * condition it can reach back past is the one the searcher itself puts
     * there -- which is the permission check. Ungrouped, `1 = 0` becomes
     * `1 = 0 OR name LIKE '%Summer%'` and an actor who may not read campaigns
     * is handed every campaign whose name matches.
     *
     * Driven through the searcher rather than over HTTP because the endpoint
     * answers 403 long before any of this runs. That is the point: this asserts
     * the guard holds on its own rather than only because something in front of
     * it caught the request first.
     */
    #[Test]
    public function searching_cannot_escape_the_permission_check(): void
    {
        // Boots the application, which is what gives the models a connection.
        // Every other test here reaches the database through `send()`, which
        // does this on the way past; this one talks to the searcher directly.
        $this->app();

        $member = User::query()->findOrFail(2);

        $this->assertFalse($member->hasPermission(Permissions::MANAGE));

        $searcher = new CampaignSearcher(new FilterManager(new CampaignTextFilter()), []);

        $state = new DatabaseSearchState($member, true);
        $state->setQuery($searcher->getQuery($member));

        (new CampaignTextFilter())->search($state, 'Summer');

        $this->assertSame(0, $state->getQuery()->count());
    }

    #[Test]
    public function it_filters_by_status(): void
    {
        $names = $this->sortedNames($this->api(['filter' => ['status' => Campaign::STATUS_ACTIVE]]));

        $this->assertSame(['House filler', 'Summer Sale'], $names);
    }

    #[Test]
    public function it_filters_by_tier(): void
    {
        $names = $this->sortedNames($this->api(['filter' => ['tier' => Campaign::TIER_HOUSE]]));

        $this->assertSame(['House filler'], $names);
    }

    #[Test]
    public function it_filters_by_advertiser(): void
    {
        $names = $this->sortedNames($this->api(['filter' => ['advertiser' => 1]]));

        $this->assertSame(['Summer Sale', 'Winter promo'], $names);
    }

    /**
     * The distinction the campaign list had no way of drawing: a campaign
     * created because a member submitted an advert sits among the ones somebody
     * sold, named after the member, and reads as one of them.
     */
    #[Test]
    public function it_separates_member_submissions_from_what_was_sold(): void
    {
        $members = $this->sortedNames($this->api(['filter' => ['source' => SourceFilter::MEMBER]]));

        $this->assertSame(['Riley'], $members);
    }

    /**
     * A campaign with no advertiser at all is direct, not neither. A plain
     * negation of "has an advertiser who is a member" would drop it from both
     * halves and lose it.
     */
    #[Test]
    public function a_campaign_with_no_advertiser_counts_as_direct(): void
    {
        $direct = $this->sortedNames($this->api(['filter' => ['source' => SourceFilter::DIRECT]]));

        $this->assertSame(['House filler', 'Summer Sale', 'Winter promo'], $direct);
    }

    /**
     * `filter[-source]=member` and `filter[source]=direct` are the same
     * question, so they must answer identically.
     */
    #[Test]
    public function negating_the_source_asks_the_same_question_the_other_way(): void
    {
        $negated = $this->sortedNames($this->api(['filter' => ['-source' => SourceFilter::MEMBER]]));
        $direct = $this->sortedNames($this->api(['filter' => ['source' => SourceFilter::DIRECT]]));

        $this->assertSame($direct, $negated);
    }

    #[Test]
    public function each_campaign_says_whether_a_member_submitted_it(): void
    {
        $body = json_decode((string) $this->api()->getBody(), true);

        $submitted = [];

        foreach ($body['data'] as $row) {
            $submitted[$row['attributes']['name']] = $row['attributes']['isMemberSubmitted'];
        }

        $this->assertSame(
            ['House filler' => false, 'Riley' => true, 'Summer Sale' => false, 'Winter promo' => false],
            // Sorted so the assertion does not depend on the order rows came
            // back in, which is what the sort tests are for.
            $this->sortedByKey($submitted)
        );
    }

    #[Test]
    public function it_sorts_by_name(): void
    {
        $this->assertSame(
            ['House filler', 'Riley', 'Summer Sale', 'Winter promo'],
            $this->names($this->api(['sort' => 'name']))
        );

        $this->assertSame(
            ['Winter promo', 'Summer Sale', 'Riley', 'House filler'],
            $this->names($this->api(['sort' => '-name']))
        );
    }

    /**
     * Ordering by a count is the one an administrator actually reaches for:
     * "what is delivering" is the first question of any morning.
     */
    #[Test]
    public function it_sorts_by_impressions(): void
    {
        $this->assertSame(
            ['Summer Sale', 'Winter promo', 'House filler', 'Riley'],
            $this->names($this->api(['sort' => '-impressions']))
        );
    }

    #[Test]
    public function it_pages_through_the_list(): void
    {
        $first = $this->names($this->api(['sort' => 'name', 'page' => ['limit' => 2]]));
        $second = $this->names($this->api(['sort' => 'name', 'page' => ['limit' => 2, 'offset' => 2]]));

        $this->assertSame(['House filler', 'Riley'], $first);
        $this->assertSame(['Summer Sale', 'Winter promo'], $second);
    }

    /**
     * The page has to be able to say how many there are, or it cannot draw a
     * pager and is back to silently truncating.
     */
    #[Test]
    public function it_reports_how_many_there_are_in_total(): void
    {
        $body = json_decode((string) $this->api(['page' => ['limit' => 2]])->getBody(), true);

        $this->assertSame(2, count($body['data']));
        $this->assertSame(4, $body['meta']['page']['total'] ?? null);
    }

    #[Test]
    public function an_ordinary_member_cannot_browse_the_list(): void
    {
        $this->assertSame(403, $this->api(['filter' => ['q' => 'summer']], as: 2)->getStatusCode());
    }

    #[Test]
    public function a_guest_cannot_browse_the_list(): void
    {
        $response = $this->send(
            $this->request('GET', '/api/placement-campaigns')->withQueryParams(['filter' => ['q' => 'summer']])
        );

        $this->assertSame(401, $response->getStatusCode());
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function sortedByKey(array $values): array
    {
        ksort($values);

        return $values;
    }
}
