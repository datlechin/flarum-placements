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

use Datlechin\Placements\Model\Advertiser;
use Datlechin\Placements\Model\Campaign;
use Datlechin\Placements\Model\Creative;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use PHPUnit\Framework\Attributes\Test;

/**
 * An administrator deleting an account, with no GDPR extension in sight.
 *
 * Three columns here point at a forum account and Flarum writes no
 * database-level foreign keys, so the ids stayed behind and every `belongsTo`
 * from them started answering null. Nothing crashed, which was the problem:
 * the rows went on claiming an advertiser was linked to somebody, a campaign
 * was created by somebody and a creative was reviewed by somebody.
 *
 * Detaching, not deleting. Deleting an account is not an erasure request, and
 * the delivery already happened.
 *
 * @see \Datlechin\Placements\Tests\integration\ForgetsAMemberTest
 */
class DeletesAnAccountTest extends TestCase
{
    use RetrievesAuthorizedUsers;
    use SeedsMemberAdvertising;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('datlechin-placements');

        $this->prepareDatabase($this->memberAdvertisingSeed());
    }

    #[Test]
    public function the_advertiser_is_unlinked(): void
    {
        $this->deleteTheAccount();

        $advertiser = Advertiser::query()->findOrFail(1);

        $this->assertNull($advertiser->user_id);
        $this->assertSame('normal', $advertiser->name);
    }

    #[Test]
    public function the_ids_on_campaigns_and_creatives_are_dropped(): void
    {
        $this->deleteTheAccount();

        $this->assertNull(Campaign::query()->findOrFail(1)->created_by);
        $this->assertNull(Creative::query()->findOrFail(1)->reviewed_by);
    }

    #[Test]
    public function the_adverts_themselves_survive(): void
    {
        $this->deleteTheAccount();

        $this->assertNotNull(Advertiser::query()->find(1));
        $this->assertNotNull(Campaign::query()->find(1));
        $this->assertNotNull(Creative::query()->find(1));
    }

    protected function deleteTheAccount(): void
    {
        $response = $this->send($this->request('DELETE', '/api/users/2', ['authenticatedAs' => 1]));

        $this->assertEquals(204, $response->getStatusCode());
        $this->assertNull(User::query()->find(2));
    }
}
