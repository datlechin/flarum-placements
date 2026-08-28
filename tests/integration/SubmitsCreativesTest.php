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

use Datlechin\Placement\Api\Resource\SubmissionResource;
use Datlechin\Placement\Model\Creative;
use Datlechin\Placement\Support\Permissions;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;

/**
 * The member submit portal.
 *
 * This is the one place in the extension where somebody who is not staff
 * writes a row that can end up on every page of the forum, so most of what is
 * here is about what a member cannot do rather than what they can.
 */
class SubmitsCreativesTest extends TestCase
{
    use RetrievesAuthorizedUsers;
    use SeedsCampaigns;

    /**
     * User 2 may submit. User 3 may not.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('datlechin-placement');

        $this->prepareDatabase([
            'users' => [
                $this->normalUser(),
                [
                    'id' => 3,
                    'username' => 'nosubmit',
                    'password' => $this->normalUser()['password'],
                    'email' => 'nosubmit@machine.local',
                    'is_email_confirmed' => 1,
                ],
            ],
            'group_user' => [
                ['user_id' => 2, 'group_id' => 4],
                ['user_id' => 3, 'group_id' => 5],
            ],
            'groups' => [
                ['id' => 4, 'name_singular' => 'Submitter', 'name_plural' => 'Submitters'],
                ['id' => 5, 'name_singular' => 'Reader', 'name_plural' => 'Readers'],
            ],
            'group_permission' => [
                ['group_id' => 4, 'permission' => Permissions::SUBMIT],
            ],
            ...$this->campaignSeed(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function api(string $method, string $path, array $options = []): ResponseInterface
    {
        return $this->send($this->request($method, "/api$path", $options));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function submit(array $attributes = [], int $as = 2): ResponseInterface
    {
        return $this->api('POST', '/placement-submissions', [
            'authenticatedAs' => $as,
            'json' => ['data' => ['attributes' => [
                'name' => 'My banner',
                'type' => 'image',
                'payload' => ['asset' => 'https://cdn.example/a.png'],
                'destinationUrl' => 'https://example.com/shop',
                ...$attributes,
            ]]],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function json(ResponseInterface $response): array
    {
        return json_decode((string) $response->getBody(), true) ?? [];
    }

    /**
     * 400 rather than 401: an unauthenticated POST carries no CSRF token, and
     * Flarum's own middleware turns it away before the endpoint is reached.
     * The endpoint's `authenticated()` is what would answer otherwise, and the
     * list below exercises it without a body in the way.
     */
    #[Test]
    public function a_guest_cannot_submit(): void
    {
        $response = $this->api('POST', '/placement-submissions', [
            'json' => ['data' => ['attributes' => ['name' => 'x', 'type' => 'image', 'payload' => []]]],
        ]);

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame(0, $this->database()->table('placement_creatives')->where('name', 'x')->count());
    }

    #[Test]
    public function a_guest_cannot_list_submissions(): void
    {
        $this->assertSame(401, $this->api('GET', '/placement-submissions')->getStatusCode());
    }

    /**
     * @return array<string, mixed>
     */
    private function forumAttributes(?int $as = null): array
    {
        $body = $this->json($this->api('GET', '/', $as === null ? [] : ['authenticatedAs' => $as]));

        return $body['data']['attributes'] ?? [];
    }

    /**
     * The client has no permission list of its own, so the forum payload says
     * in one boolean whether to draw the tab at all.
     */
    #[Test]
    public function the_forum_payload_says_who_may_submit(): void
    {
        $this->assertTrue($this->forumAttributes(2)['canSubmitPlacements']);
        $this->assertFalse($this->forumAttributes(3)['canSubmitPlacements']);
        $this->assertFalse($this->forumAttributes()['canSubmitPlacements']);
    }

    /**
     * The form is built from this list, so a type that would be refused on
     * save must never reach it.
     */
    #[Test]
    public function the_submittable_types_leave_out_the_ones_that_run_code(): void
    {
        $keys = array_column($this->forumAttributes(2)['placementSubmittableTypes'], 'key');

        $this->assertContains('image', $keys);
        $this->assertContains('text', $keys);
        $this->assertContains('rich_text', $keys);
        $this->assertContains('logo_wall', $keys);

        $this->assertNotContains('raw_html', $keys);
        $this->assertNotContains('network', $keys);
    }

    #[Test]
    public function the_submittable_types_are_not_sent_to_somebody_who_cannot_submit(): void
    {
        $this->assertArrayNotHasKey('placementSubmittableTypes', $this->forumAttributes(3));
        $this->assertArrayNotHasKey('placementSubmittableTypes', $this->forumAttributes());
    }

    #[Test]
    public function a_member_without_the_permission_cannot_submit(): void
    {
        $this->assertSame(403, $this->submit(as: 3)->getStatusCode());
    }

    #[Test]
    public function a_member_with_the_permission_can_submit(): void
    {
        $response = $this->submit();

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('My banner', $this->json($response)['data']['attributes']['name']);
    }

    /**
     * The whole point of the queue: if a member could name their own status,
     * review would be optional.
     */
    #[Test]
    public function a_submission_arrives_pending(): void
    {
        $response = $this->submit();

        $this->assertSame(Creative::STATUS_PENDING, $this->json($response)['data']['attributes']['status']);
        $this->assertSame(Creative::STATUS_PENDING, $this->database()->table('placement_creatives')->where('name', 'My banner')->value('status'));
    }

    /**
     * Refused outright rather than quietly ignored: `status` is not writable
     * on this resource, so naming it at all is an error.
     */
    #[Test]
    public function a_member_cannot_set_the_status_of_their_own_submission(): void
    {
        $this->assertSame(403, $this->submit(['status' => 'approved'])->getStatusCode());
        $this->assertSame(0, $this->database()->table('placement_creatives')->where('name', 'My banner')->count());
    }

    /**
     * Nor by editing one afterwards, which is the same hole with an extra
     * request in front of it.
     */
    #[Test]
    public function a_member_cannot_approve_their_own_submission_by_editing_it(): void
    {
        $this->submit(['name' => 'Mine']);

        $id = (int) $this->database()->table('placement_creatives')->where('name', 'Mine')->value('id');

        $response = $this->api('PATCH', "/placement-submissions/$id", [
            'authenticatedAs' => 2,
            'json' => ['data' => ['attributes' => ['status' => 'approved']]],
        ]);

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame(Creative::STATUS_PENDING, $this->database()->table('placement_creatives')->where('id', $id)->value('status'));
    }

    /**
     * A member is an advertiser, so submitting makes them one. Nothing is
     * created for somebody who never submits.
     */
    #[Test]
    public function submitting_creates_an_advertiser_and_a_campaign_for_them(): void
    {
        $this->submit();

        $advertiser = $this->database()->table('placement_advertisers')->where('user_id', 2)->first();

        $this->assertNotNull($advertiser);

        $campaign = $this->database()->table('placement_campaigns')->where('advertiser_id', $advertiser->id)->first();

        $this->assertNotNull($campaign);
        $this->assertSame(2, (int) $campaign->created_by);
    }

    #[Test]
    public function a_second_submission_reuses_the_first_campaign(): void
    {
        $this->submit(['name' => 'One']);
        $this->submit(['name' => 'Two']);

        $this->assertSame(1, $this->database()->table('placement_advertisers')->where('user_id', 2)->count());
        // One more than the seed's, which belongs to nobody.
        $this->assertSame(2, $this->database()->table('placement_campaigns')->count());
    }

    /**
     * A creative runs only when it has been approved *and* assigned to a slot.
     * A member can do neither, which is what makes an active campaign safe.
     */
    #[Test]
    public function a_submission_arrives_with_no_slot_assigned(): void
    {
        $this->submit();

        $id = $this->database()->table('placement_creatives')->where('name', 'My banner')->value('id');

        $this->assertSame(0, $this->database()->table('placement_assignments')->where('creative_id', $id)->count());
    }

    /**
     * Raw HTML and network containers run author-controlled code on every page
     * of the forum. They are authored in the admin panel, never submitted.
     */
    #[Test]
    public function a_type_that_runs_code_cannot_be_submitted(): void
    {
        foreach (['raw_html', 'network'] as $type) {
            $response = $this->submit(['type' => $type, 'payload' => ['html' => '<b>x</b>', 'height' => 100, 'attributes' => []]]);

            $this->assertSame(400, $response->getStatusCode(), "type [$type] should be refused");
        }
    }

    #[Test]
    public function a_type_that_does_not_exist_is_refused(): void
    {
        $this->assertSame(400, $this->submit(['type' => 'nonsense'])->getStatusCode());
    }

    #[Test]
    public function a_payload_that_does_not_match_its_type_is_refused(): void
    {
        $this->assertSame(422, $this->submit(['payload' => ['alt' => 'no image at all']])->getStatusCode());
    }

    /**
     * The value reaches an `href`, where a `javascript:` scheme is script
     * execution rather than navigation.
     */
    #[Test]
    public function a_destination_that_is_not_http_is_refused(): void
    {
        $this->assertSame(422, $this->submit(['destinationUrl' => 'javascript:alert(1)'])->getStatusCode());
    }

    #[Test]
    public function a_member_sees_only_their_own_submissions(): void
    {
        $this->submit(['name' => 'Mine']);

        $body = $this->json($this->api('GET', '/placement-submissions', ['authenticatedAs' => 2]));
        $names = array_map(fn (array $row): string => $row['attributes']['name'], $body['data']);

        // Not 'Acme leaderboard', which belongs to the seed's advertiser and
        // to nobody's user account.
        $this->assertSame(['Mine'], $names);
    }

    #[Test]
    public function a_member_cannot_read_another_members_submission(): void
    {
        $this->submit(['name' => 'Mine'], as: 2);

        $id = $this->database()->table('placement_creatives')->where('name', 'Mine')->value('id');

        // User 1 is an administrator, and still gets nothing here: this
        // resource is scoped by ownership rather than by permission, and an
        // administrator reads the queue through the admin panel instead.
        $this->assertSame(404, $this->api('GET', "/placement-submissions/$id", ['authenticatedAs' => 1])->getStatusCode());
    }

    #[Test]
    public function a_member_cannot_read_a_staff_created_creative_through_this_resource(): void
    {
        $this->assertSame(404, $this->api('GET', '/placement-submissions/1', ['authenticatedAs' => 2])->getStatusCode());
    }

    #[Test]
    public function a_member_cannot_edit_a_staff_created_creative(): void
    {
        $response = $this->api('PATCH', '/placement-submissions/1', [
            'authenticatedAs' => 2,
            'json' => ['data' => ['attributes' => ['name' => 'Hijacked']]],
        ]);

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('Acme leaderboard', $this->database()->table('placement_creatives')->where('id', 1)->value('name'));
    }

    #[Test]
    public function a_member_cannot_delete_a_staff_created_creative(): void
    {
        $this->assertSame(404, $this->api('DELETE', '/placement-submissions/1', ['authenticatedAs' => 2])->getStatusCode());
        $this->assertSame(1, $this->database()->table('placement_creatives')->where('id', 1)->count());
    }

    /**
     * Editing something already approved sends it back to the queue. Without
     * it, "approve once, edit freely" is a way to put anything at all on every
     * page of the forum.
     */
    #[Test]
    public function editing_an_approved_submission_sends_it_back_for_review(): void
    {
        $this->submit(['name' => 'Mine']);

        $id = (int) $this->database()->table('placement_creatives')->where('name', 'Mine')->value('id');

        $this->database()->table('placement_creatives')->where('id', $id)->update([
            'status' => Creative::STATUS_APPROVED,
            'reviewed_by' => 1,
        ]);

        $response = $this->api('PATCH', "/placement-submissions/$id", [
            'authenticatedAs' => 2,
            'json' => ['data' => ['attributes' => ['name' => 'Mine, edited']]],
        ]);

        $this->assertSame(200, $response->getStatusCode());

        $row = $this->database()->table('placement_creatives')->where('id', $id)->first();

        $this->assertSame(Creative::STATUS_PENDING, $row->status);
        $this->assertNull($row->reviewed_by);
    }

    /**
     * Somebody has to read each of these. A member who can put a hundred in
     * front of them has made the queue useless for everybody else.
     */
    #[Test]
    public function a_member_cannot_queue_more_than_the_limit(): void
    {
        for ($i = 0; $i < SubmissionResource::PENDING_LIMIT; $i++) {
            $this->assertSame(201, $this->submit(['name' => "Banner $i"])->getStatusCode());
        }

        $this->assertSame(403, $this->submit(['name' => 'One too many'])->getStatusCode());
    }

    /**
     * The limit counts what is waiting, not what was ever sent, so deciding on
     * one frees a slot in the queue.
     */
    #[Test]
    public function deciding_on_one_frees_a_place_in_the_queue(): void
    {
        for ($i = 0; $i < SubmissionResource::PENDING_LIMIT; $i++) {
            $this->submit(['name' => "Banner $i"]);
        }

        $this->database()->table('placement_creatives')->where('name', 'Banner 0')->update(['status' => Creative::STATUS_APPROVED]);

        $this->assertSame(201, $this->submit(['name' => 'Room for one more'])->getStatusCode());
    }
}
