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

use Datlechin\Placement\Support\Permissions;
use Flarum\Group\Group;
use Flarum\Testing\integration\ConsoleTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * The upgrade path from the extension most forums are actually running.
 *
 * davwheat/flarum-ext-ads has 11,320 lifetime installs and no 2.x branch, so a
 * good number of forums are on Flarum 1.x partly because their advertising
 * would not come with them.
 */
class ImportsDavwheatTest extends ConsoleTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('datlechin-placement');
    }

    protected function seedDavwheat(): void
    {
        // Through the helper rather than as rows: the settings repository is
        // read once at boot, so a row inserted afterwards is never seen.
        $this->setting('davwheat-ads.ad-code.header', '<div>header advert</div>');
        $this->setting('davwheat-ads.ad-code.sidebar', '<div>sidebar advert</div>');
        $this->setting('davwheat-ads.ad-code.between_posts', '<div>between advert</div>');
        $this->setting('davwheat-ads.ad-code.footer', '   ');
        $this->setting('davwheat-ads.between-n-posts', '5');

        $this->prepareDatabase([
            'group_permission' => [
                ['group_id' => Group::MEMBER_ID, 'permission' => 'davwheat-ads.bypass-ads'],
            ],
        ]);
    }

    #[Test]
    public function it_says_so_when_there_is_nothing_to_import(): void
    {
        $this->assertStringContainsString('Nothing to import', $this->runCommand(['command' => 'placement:import-davwheat']));
    }

    #[Test]
    public function a_dry_run_writes_nothing(): void
    {
        $this->seedDavwheat();

        $output = $this->runCommand(['command' => 'placement:import-davwheat', '--dry-run' => true]);

        $this->assertStringContainsString('Would import 3', $output);
        $this->assertSame(0, $this->database()->table('placement_campaigns')->count());
        $this->assertSame(0, $this->database()->table('placement_creatives')->count());
    }

    #[Test]
    public function it_brings_each_advert_across_to_its_slot(): void
    {
        $this->seedDavwheat();

        $this->runCommand(['command' => 'placement:import-davwheat']);

        $slots = $this->database()->table('placement_assignments')->orderBy('placement_key')->pluck('placement_key');

        $this->assertSame(['index_above_list', 'index_sidebar', 'post_footer'], $slots->all());
    }

    #[Test]
    public function an_empty_advert_is_not_imported(): void
    {
        // davwheat's footer field is present but blank here. Importing it would
        // create a creative that renders nothing.
        $this->seedDavwheat();

        $this->runCommand(['command' => 'placement:import-davwheat']);

        $this->assertSame(0, $this->database()->table('placement_assignments')->where('placement_key', 'footer')->count());
    }

    #[Test]
    public function nothing_starts_serving_by_itself(): void
    {
        // An import must not put markup nobody has looked at onto every page of
        // the forum.
        $this->seedDavwheat();

        $this->runCommand(['command' => 'placement:import-davwheat']);

        $this->assertSame('draft', $this->database()->table('placement_campaigns')->value('status'));
        $this->assertSame('draft', $this->database()->table('placement_creatives')->value('status'));
    }

    #[Test]
    public function the_imported_campaign_is_a_house_campaign(): void
    {
        // So it never competes with a paid campaign and never has a cap to
        // reach.
        $this->seedDavwheat();

        $this->runCommand(['command' => 'placement:import-davwheat']);

        $this->assertEquals(1, $this->database()->table('placement_campaigns')->value('is_house'));
    }

    #[Test]
    public function between_n_posts_becomes_the_slots_repeat_interval(): void
    {
        $this->seedDavwheat();

        $this->runCommand(['command' => 'placement:import-davwheat']);

        $this->assertSame(5, (int) $this->database()->table('placement_settings')->where('key', 'post_footer')->value('every_n'));
    }

    #[Test]
    public function whoever_was_ad_free_before_stays_ad_free(): void
    {
        // The one thing an upgrade must not silently change: a supporter who
        // paid to browse without advertising should not start seeing it
        // because the extension was replaced.
        $this->seedDavwheat();

        $this->runCommand(['command' => 'placement:import-davwheat']);

        $this->assertSame(1, $this->database()->table('group_permission')
            ->where('group_id', Group::MEMBER_ID)
            ->where('permission', Permissions::VIEW_WITHOUT_ADS)
            ->count());
    }

    #[Test]
    public function it_says_plainly_what_still_has_to_be_done(): void
    {
        // The imported creatives are raw HTML, which needs a config.php flag
        // this command deliberately does not set.
        $this->seedDavwheat();

        $output = $this->runCommand(['command' => 'placement:import-davwheat']);

        $this->assertStringContainsString('config.php', $output);
        $this->assertStringContainsString('drafts', $output);
    }
}
