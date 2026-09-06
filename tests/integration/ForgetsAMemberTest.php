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

use Datlechin\Placements\Gdpr\AdvertisingData;
use Datlechin\Placements\Model\Advertiser;
use Datlechin\Placements\Model\Assignment;
use Datlechin\Placements\Model\Campaign;
use Datlechin\Placements\Model\Creative;
use Datlechin\Placements\Model\Stat;
use Flarum\Gdpr\Models\ErasureRequest;
use Flarum\Http\UrlGenerator;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use Illuminate\Contracts\Filesystem\Factory;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * What flarum/gdpr does with a member's advertising.
 *
 * A member who submits an advert gets an advertiser record holding their name
 * and their email address, and a campaign named after them. None of that
 * belongs to the forum once they have asked to be forgotten, and none of it
 * was being touched.
 *
 * The delivery figures are the other half of nearly every assertion here. They
 * are hourly totals keyed by campaign, creative, slot and device, they name
 * nobody, and a forum whose totals stopped adding up after an erasure would be
 * worse off than one that never offered it.
 *
 * @see \Datlechin\Placements\Tests\integration\DeletesAnAccountTest
 */
class ForgetsAMemberTest extends TestCase
{
    use RetrievesAuthorizedUsers;
    use SeedsMemberAdvertising;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('flarum-gdpr', 'datlechin-placements');

        $this->prepareDatabase($this->memberAdvertisingSeed());
    }

    #[Test]
    public function their_advertising_is_in_the_export(): void
    {
        $files = $this->data()->export();

        $this->assertNotNull($files);

        $names = array_merge(...array_map('array_keys', $files));

        $this->assertEqualsCanonicalizing([
            'placements/advertiser.json',
            'placements/campaign-1.json',
            'placements/creative-1.json',
        ], $names);
    }

    #[Test]
    public function the_export_carries_the_details_and_not_the_report_token(): void
    {
        $files = $this->data()->export();

        $this->assertNotNull($files);

        $advertiser = json_decode($files[0]['placements/advertiser.json'], true);

        $this->assertSame('normal@machine.local', $advertiser['contact_email']);
        $this->assertSame('Pays by bank transfer.', $advertiser['notes']);

        // The one thing in the record an outsider can use. An export is a file
        // that leaves the forum; it is not a place to reissue a report URL.
        $this->assertArrayNotHasKey('report_token', $advertiser);
    }

    #[Test]
    public function somebody_who_never_advertised_has_nothing_to_export(): void
    {
        $this->app();

        Advertiser::query()->where('id', 1)->update(['user_id' => null]);

        $this->assertNull($this->data()->export());
    }

    #[Test]
    public function anonymising_takes_the_name_the_email_and_the_report_link(): void
    {
        $this->data()->anonymize();

        $advertiser = Advertiser::query()->findOrFail(1);

        // The same name flarum/gdpr is about to give the account, so the two
        // still line up in the admin list afterwards.
        $this->assertSame('Anonymous7', $advertiser->name);
        $this->assertNull($advertiser->contact_email);
        $this->assertNull($advertiser->notes);
        $this->assertNull($advertiser->report_token);
    }

    #[Test]
    public function anonymising_renames_the_campaign_that_was_named_after_them(): void
    {
        $this->data()->anonymize();

        $this->assertSame('Anonymous7', Campaign::query()->findOrFail(1)->name);

        // Somebody else's campaign, under somebody else's advertiser.
        $this->assertSame('Cloudforge annual', Campaign::query()->findOrFail(2)->name);
    }

    #[Test]
    public function anonymising_keeps_the_adverts_running(): void
    {
        $this->data()->anonymize();

        $this->assertNotNull(Creative::query()->find(1));
        $this->assertNotNull(Assignment::query()->find(1));
        $this->assertSame(412, Stat::query()->findOrFail(1)->impressions);
    }

    #[Test]
    public function erasing_takes_the_record_and_every_advert_under_it(): void
    {
        $this->data()->delete();

        $this->assertNull(Advertiser::query()->find(1));
        $this->assertNull(Campaign::query()->find(1));
        $this->assertNull(Creative::query()->find(1));

        // Through the campaign rather than with the advertiser: deleting an
        // advertiser detaches its campaigns instead, which is right when an
        // administrator removes one and wrong when the adverts are the thing
        // being erased.
        $this->assertNull(Assignment::query()->find(1));
    }

    #[Test]
    public function erasing_leaves_everybody_else_alone(): void
    {
        $this->data()->delete();

        $this->assertSame(412, Stat::query()->findOrFail(1)->impressions);

        $this->assertNotNull(Advertiser::query()->find(2));
        $this->assertNotNull(Campaign::query()->find(2));
    }

    #[Test]
    public function erasing_detaches_the_ids_that_outlive_the_record(): void
    {
        $this->data()->delete();

        // flarum/gdpr erases by calling delete() on the user model, and
        // `Deleted` is only dispatched by the API resource layer, so this
        // never reaches the listener and has to happen on this path too.
        $this->assertNull(Campaign::query()->findOrFail(2)->created_by);
    }

    protected function data(): AdvertisingData
    {
        $container = $this->app()->getContainer();

        // Built the way flarum/gdpr builds it: `new $type(...)`, not through
        // the container, so the constructor has to keep matching.
        $request = new ErasureRequest();
        $request->id = 7;

        return new AdvertisingData(
            User::query()->findOrFail(2),
            $request,
            $container->make(Factory::class),
            $container->make(SettingsRepositoryInterface::class),
            $container->make(UrlGenerator::class),
            $container->make(TranslatorInterface::class),
        );
    }
}
