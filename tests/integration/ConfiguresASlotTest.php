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

use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;

/**
 * Changing a slot's settings for the first time.
 *
 * A slot with no row is not unconfigured, it is default-configured, so this
 * resource has no create endpoint at all: `find()` hands back an unsaved model
 * for any key the registry knows, and the first `PATCH` writes the row.
 *
 * The method is therefore load-bearing, and nothing tested it. The admin panel
 * sent the wrong one -- `Model.save()` picks `POST` whenever `exists` is false,
 * which it is for every model the store did not receive from the server, and no
 * route answers `POST /placement-settings/{key}`. So configuring a slot failed
 * on the first attempt for every slot on every forum, and the failure was
 * swallowed: the switch simply sprang back.
 */
class ConfiguresASlotTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('datlechin-placements');

        $this->prepareDatabase([
            'users' => [$this->normalUser()],
            'group_permission' => [],
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function write(string $method, string $key, array $attributes, int $as = 1): ResponseInterface
    {
        return $this->send(
            $this->request($method, "/api/placement-settings/$key", [
                'authenticatedAs' => $as,
                'json' => ['data' => ['type' => 'placement-settings', 'id' => $key, 'attributes' => $attributes]],
            ])
        );
    }

    private function stored(string $key): ?object
    {
        return $this->database()->table('placement_settings')->where('key', $key)->first();
    }

    /**
     * The shape the admin panel has to send. There is no row for this slot yet.
     */
    #[Test]
    public function patching_a_slot_that_has_no_row_yet_creates_one(): void
    {
        $this->assertNull($this->stored('index_above_list'), 'the fixture should start with no row');

        $response = $this->write('PATCH', 'index_above_list', ['enabled' => false]);

        $this->assertSame(200, $response->getStatusCode());

        $row = $this->stored('index_above_list');

        $this->assertNotNull($row);
        $this->assertSame(0, (int) $row->enabled);
    }

    /**
     * The method the admin panel used to send, and the reason first-time
     * configuration never worked.
     *
     * This is not a route anybody should add: the resource deliberately has no
     * create endpoint, because an administrator configures a slot and never
     * invents one. It is asserted so that the client is never quietly changed
     * back to sending it.
     */
    #[Test]
    public function posting_to_a_slot_is_not_a_route_at_all(): void
    {
        $response = $this->write('POST', 'index_above_list', ['enabled' => false]);

        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertNull($this->stored('index_above_list'));
    }

    #[Test]
    public function a_second_change_updates_the_row_rather_than_adding_another(): void
    {
        $this->write('PATCH', 'index_above_list', ['enabled' => false]);
        $this->write('PATCH', 'index_above_list', ['maxFill' => 3]);

        $this->assertSame(1, $this->database()->table('placement_settings')->count());

        $row = $this->stored('index_above_list');

        $this->assertSame(3, (int) $row->max_fill);
        // The first change survives the second: a slot is configured one
        // control at a time, and each save carries only what moved.
        $this->assertSame(0, (int) $row->enabled);
    }

    /**
     * A key the registry does not know is refused rather than written, so the
     * table cannot fill with settings for slots nothing renders.
     */
    #[Test]
    public function a_slot_nothing_declares_cannot_be_configured(): void
    {
        $response = $this->write('PATCH', 'not_a_real_slot', ['enabled' => false]);

        $this->assertSame(400, $response->getStatusCode());
        $this->assertNull($this->stored('not_a_real_slot'));
    }

    #[Test]
    public function an_ordinary_member_cannot_configure_a_slot(): void
    {
        $this->assertSame(403, $this->write('PATCH', 'index_above_list', ['enabled' => false], as: 2)->getStatusCode());
        $this->assertNull($this->stored('index_above_list'));
    }
}
