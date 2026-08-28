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

use Datlechin\Placement\Console\ExportCommand;
use Datlechin\Placement\Model\Campaign;
use Datlechin\Placement\Model\Creative;
use Datlechin\Placement\Support\Settings;
use Flarum\Testing\integration\ConsoleTestCase;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use PHPUnit\Framework\Attributes\Test;

/**
 * Moving a configuration between forums, and back again.
 *
 * The round trip is the test that matters: a bundle nobody can read back is
 * a backup that is not one.
 */
class ExportsAndImportsTest extends ConsoleTestCase
{
    use RetrievesAuthorizedUsers;
    use SeedsCampaigns;

    private string $file;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('datlechin-placement');

        $this->file = tempnam(sys_get_temp_dir(), 'placement').'.json';

        $seed = $this->campaignSeed();

        // A staff-created advertiser with a campaign of its own. The shared
        // seed's campaign has no advertiser at all, which is what a house
        // campaign looks like, so both shapes are covered.
        $seed['placement_advertisers'][] = [
            'id' => 1,
            'name' => 'Acme Corp',
            'contact_email' => 'ads@acme.example',
            'user_id' => null,
        ];
        $seed['placement_campaigns'][] = [
            'id' => 2,
            'advertiser_id' => 1,
            'name' => 'Q1 sponsorship',
            'status' => Campaign::STATUS_ACTIVE,
            'tier' => Campaign::TIER_SPONSORSHIP,
            'is_house' => false,
            'pacing' => Campaign::PACING_ASAP,
            'frequency_window' => Campaign::WINDOW_DAY,
            'impressions' => 0,
            'clicks' => 0,
        ];
        $seed['placement_creatives'][] = [
            'id' => 2,
            'campaign_id' => 2,
            'name' => 'Sponsorship banner',
            'type' => 'image',
            'status' => Creative::STATUS_APPROVED,
            'weight' => 10,
            'destination_url' => 'https://acme.example/q1',
            'payload' => json_encode(['asset' => 'https://example.com/q1.png']),
            'impressions' => 0,
            'viewable_impressions' => 0,
            'clicks' => 0,
        ];

        // An advertiser belonging to a member, whose inventory must not travel.
        $seed['placement_advertisers'][] = [
            'id' => 99,
            'name' => 'A member',
            'user_id' => 2,
        ];
        $seed['placement_campaigns'][] = [
            'id' => 99,
            'advertiser_id' => 99,
            'name' => 'Member campaign',
            'status' => Campaign::STATUS_ACTIVE,
            'tier' => Campaign::TIER_REMNANT,
            'is_house' => false,
            'pacing' => Campaign::PACING_ASAP,
            'frequency_window' => Campaign::WINDOW_DAY,
            'impressions' => 0,
            'clicks' => 0,
        ];
        $seed['placement_creatives'][] = [
            'id' => 99,
            'campaign_id' => 99,
            'name' => 'A member advert',
            'type' => 'image',
            'status' => Creative::STATUS_PENDING,
            'weight' => 10,
            'payload' => json_encode(['asset' => 'https://example.com/member.png']),
            'impressions' => 0,
            'viewable_impressions' => 0,
            'clicks' => 0,
        ];

        $this->prepareDatabase([
            'users' => [$this->normalUser()],
            'group_permission' => [],
            ...$seed,
        ]);
    }

    protected function tearDown(): void
    {
        if (is_file($this->file)) {
            unlink($this->file);
        }

        parent::tearDown();
    }

    /**
     * @return array<string, mixed>
     */
    private function export(): array
    {
        $this->runCommand(['command' => 'placement:export', '--output' => $this->file]);

        return json_decode((string) file_get_contents($this->file), true);
    }

    /**
     * @param  array<string, mixed>  $bundle
     */
    private function write(array $bundle): void
    {
        file_put_contents($this->file, json_encode($bundle));
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function import(array $extra = []): string
    {
        return $this->runCommand(['command' => 'placement:import', 'file' => $this->file, ...$extra]);
    }

    #[Test]
    public function it_exports_what_the_staff_set_up(): void
    {
        $bundle = $this->export();

        $this->assertSame(ExportCommand::FORMAT, $bundle['format']);

        // The seed's campaign has no advertiser, which is what a house
        // campaign looks like -- so it travels in the top-level list.
        $this->assertSame(['Acme Corp'], array_column($bundle['advertisers'], 'name'));
        $this->assertSame(['Q1 sponsorship'], array_column($bundle['advertisers'][0]['campaigns'], 'name'));
        $this->assertSame(['Acme'], array_column($bundle['campaigns'], 'name'));
        $this->assertSame(['Acme leaderboard'], array_column($bundle['campaigns'][0]['creatives'], 'name'));
        $this->assertSame(
            ['index_above_list'],
            array_column($bundle['campaigns'][0]['creatives'][0]['assignments'], 'placement_key')
        );
    }

    /**
     * A `user_id` points at an account that does not exist on the machine the
     * file is going to.
     */
    #[Test]
    public function it_leaves_member_submissions_out(): void
    {
        $bundle = $this->export();

        $this->assertNotContains('A member', array_column($bundle['advertisers'], 'name'));
        $this->assertNotContains('Member campaign', array_column($bundle['campaigns'], 'name'));
    }

    /**
     * A report link is the whole of an advertiser's authentication, so a file
     * that carried one would be a working login.
     */
    #[Test]
    public function it_never_writes_a_report_token(): void
    {
        $this->database()->table('placement_advertisers')->where('id', 1)->update([
            'report_token' => 'a-secret-token-nobody-should-see',
        ]);

        $this->assertStringNotContainsString('a-secret-token', (string) file_get_contents($this->fileAfterExport()));
    }

    private function fileAfterExport(): string
    {
        $this->export();

        return $this->file;
    }

    /**
     * Counts describe one forum's traffic and would be wrong the moment they
     * landed anywhere else.
     */
    #[Test]
    public function it_never_writes_a_count(): void
    {
        $this->database()->table('placement_creatives')->where('id', 1)->update(['impressions' => 4321, 'clicks' => 99]);

        $creative = $this->export()['campaigns'][0]['creatives'][0];

        $this->assertArrayNotHasKey('impressions', $creative);
        $this->assertArrayNotHasKey('clicks', $creative);
    }

    #[Test]
    public function it_reads_back_what_it_wrote(): void
    {
        $bundle = $this->export();

        // A different forum: everything the file describes is gone.
        $this->database()->table('placement_assignments')->delete();
        $this->database()->table('placement_creatives')->delete();
        $this->database()->table('placement_campaigns')->delete();
        $this->database()->table('placement_advertisers')->delete();

        $this->write($bundle);
        $this->import();

        $this->assertSame(1, $this->database()->table('placement_advertisers')->where('name', 'Acme Corp')->count());
        $this->assertSame(1, $this->database()->table('placement_campaigns')->where('name', 'Q1 sponsorship')->count());

        $creative = $this->database()->table('placement_creatives')->where('name', 'Acme leaderboard')->first();

        $this->assertNotNull($creative);
        $this->assertSame('image', $creative->type);
        $this->assertSame('https://example.com/offer', $creative->destination_url);
        $this->assertSame(1, $this->database()->table('placement_assignments')->where('creative_id', $creative->id)->count());
    }

    /**
     * A bundle is somebody else's decisions about somebody else's forum, and a
     * creative's approval travels with it -- so the usual review gate is
     * already spent by the time it lands.
     */
    #[Test]
    public function everything_arrives_paused(): void
    {
        $bundle = $this->export();

        $this->assertSame(Campaign::STATUS_ACTIVE, $bundle['campaigns'][0]['status']);

        $this->database()->table('placement_campaigns')->where('id', 1)->delete();

        $this->write($bundle);
        $this->import();

        $this->assertSame(
            Campaign::STATUS_PAUSED,
            $this->database()->table('placement_campaigns')->where('name', 'Acme')->value('status')
        );
    }

    /**
     * Importing the same file twice is what somebody does after editing it, so
     * it has to update rather than duplicate.
     */
    #[Test]
    public function importing_twice_does_not_duplicate(): void
    {
        $this->export();
        $this->import();
        $this->import();

        $this->assertSame(1, $this->database()->table('placement_advertisers')->where('name', 'Acme Corp')->count());
        $this->assertSame(1, $this->database()->table('placement_creatives')->where('name', 'Acme leaderboard')->count());
        $this->assertSame(1, $this->database()->table('placement_assignments')->where('placement_key', 'index_above_list')->count());
    }

    #[Test]
    public function a_dry_run_writes_nothing(): void
    {
        $bundle = $this->export();

        $this->database()->table('placement_advertisers')->delete();

        $this->write($bundle);
        $output = $this->import(['--dry-run' => true]);

        $this->assertStringContainsString('Would import', $output);
        $this->assertSame(0, $this->database()->table('placement_advertisers')->count());
    }

    /**
     * Refused whole rather than read as far as it parses: a file from a later
     * version may hold fields this build would silently drop.
     */
    #[Test]
    public function a_file_of_the_wrong_format_is_refused(): void
    {
        $this->write([
            'format' => 99,
            'advertisers' => [['name' => 'From the future', 'campaigns' => []]],
        ]);

        $this->assertStringContainsString('format', $this->import());
        $this->assertSame(0, $this->database()->table('placement_advertisers')->where('name', 'From the future')->count());
    }

    /**
     * A file is a text file somebody may have edited, so it gets the same
     * checks a form does.
     */
    #[Test]
    public function an_unsafe_destination_in_a_file_is_dropped(): void
    {
        $bundle = $this->export();
        $bundle['campaigns'][0]['creatives'][0]['destination_url'] = 'javascript:alert(1)';

        $this->write($bundle);
        $this->import();

        $this->assertNull($this->database()->table('placement_creatives')->where('name', 'Acme leaderboard')->value('destination_url'));
    }

    #[Test]
    public function a_creative_whose_payload_is_not_valid_is_skipped(): void
    {
        $bundle = $this->export();
        $bundle['campaigns'][0]['creatives'][0]['name'] = 'Broken banner';
        $bundle['campaigns'][0]['creatives'][0]['payload'] = ['alt' => 'no image at all'];

        $this->write($bundle);
        $output = $this->import();

        $this->assertStringContainsString('Broken banner', $output);
        $this->assertSame(0, $this->database()->table('placement_creatives')->where('name', 'Broken banner')->count());
    }

    /**
     * A slot key another forum had is not a key this one renders, and an
     * assignment pointing at nothing is indistinguishable from a targeting
     * problem.
     */
    #[Test]
    public function an_assignment_to_a_slot_this_forum_lacks_is_dropped(): void
    {
        $bundle = $this->export();
        $bundle['campaigns'][0]['creatives'][0]['assignments'][] = [
            'placement_key' => 'some_other_forums_slot',
            'weight' => null,
            'enabled' => true,
        ];

        $this->write($bundle);
        $output = $this->import();

        $this->assertStringContainsString('some_other_forums_slot', $output);
        $this->assertSame(0, $this->database()->table('placement_assignments')->where('placement_key', 'some_other_forums_slot')->count());
    }

    /**
     * A file could otherwise write any setting on the forum by naming it.
     */
    #[Test]
    public function it_writes_only_the_settings_the_exporter_names(): void
    {
        $bundle = $this->export();
        $bundle['settings'][Settings::TIMEZONE] = 'Europe/Berlin';
        $bundle['settings']['forum_title'] = 'Hijacked';

        $this->write($bundle);
        $this->import(['--settings' => true]);

        $this->assertSame('Europe/Berlin', $this->database()->table('settings')->where('key', Settings::TIMEZONE)->value('value'));
        $this->assertNotSame('Hijacked', $this->database()->table('settings')->where('key', 'forum_title')->value('value'));
    }

    /**
     * Settings are a separate decision from inventory: somebody moving a
     * campaign between forums rarely wants the other forum's ads.txt too.
     */
    #[Test]
    public function settings_are_left_alone_without_the_flag(): void
    {
        $bundle = $this->export();
        $bundle['settings'][Settings::TIMEZONE] = 'Europe/Berlin';

        $this->write($bundle);
        $this->import();

        $this->assertNotSame('Europe/Berlin', $this->database()->table('settings')->where('key', Settings::TIMEZONE)->value('value'));
    }
}
