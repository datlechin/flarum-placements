<?php

/*
 * This file is part of datlechin/flarum-placements.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\Placements\Console;

use Datlechin\Placements\Model\Assignment;
use Datlechin\Placements\Model\Campaign;
use Datlechin\Placements\Model\Creative;
use Datlechin\Placements\Model\PlacementSetting;
use Datlechin\Placements\Support\Permissions;
use Flarum\Console\AbstractCommand;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Database\ConnectionInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * Brings a davwheat/flarum-ext-ads configuration across.
 *
 * That extension has 11,320 lifetime installs and still around fifty a month,
 * four years after its last release and with no 2.x branch at all — which
 * means a good number of those forums are on Flarum 1.x partly *because* their
 * advertising would not come with them. Rebuilding six textareas by hand is
 * not hard, but it is the sort of small friction that stops an upgrade
 * happening at all.
 *
 * Everything it holds is six flat settings keys and one permission, so this is
 * a short command rather than a subsystem.
 *
 * Two things it deliberately does not do. It never enables anything: every
 * creative arrives as a draft, so nothing appears on the forum until somebody
 * has looked at it. And it does not turn on raw HTML — those creatives cannot
 * serve until the `config.php` flag is set and the permission granted, and the
 * command says so rather than quietly opening the door.
 */
class ImportDavwheatCommand extends AbstractCommand
{
    /**
     * davwheat's location keys, and the slot each becomes here.
     */
    public const SLOTS = [
        'header' => 'index_above_list',
        'sidebar' => 'index_sidebar',
        'footer' => 'footer',
        'between_posts' => 'post_footer',
        'discussion_header' => 'discussion_after_op',
        'discussion_sidebar' => 'discussion_sidebar',
    ];

    public function __construct(
        protected SettingsRepositoryInterface $settings,
        protected ConnectionInterface $db,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('placements:import-davwheat')
            ->setDescription('Bring a davwheat/flarum-ext-ads configuration into this extension')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report what would be imported without writing anything');
    }

    protected function fire(): int
    {
        $dryRun = (bool) $this->input->getOption('dry-run');
        $found = $this->found();

        if ($found === []) {
            $this->info('Nothing to import: no davwheat-ads settings found.');

            return 0;
        }

        $this->info(($dryRun ? 'Would import' : 'Importing').' '.count($found).' advert(s).');

        if (! $dryRun) {
            $this->write($found);
            $this->carryPermission();
        }

        foreach ($found as $slot => $advert) {
            $this->info("  $slot  <-  {$advert['from']}");
        }

        $this->info('');
        $this->info($dryRun
            ? 'Nothing was written. Run again without --dry-run to import.'
            : 'Imported as drafts. Nothing shows on the forum until you approve them.');

        $this->info('These are raw HTML creatives, so they also need the raw_html permission and');
        $this->info("  'datlechin-placements' => ['raw_html' => true],");
        $this->info('in config.php before they can serve.');

        return 0;
    }

    /**
     * The adverts davwheat's extension actually has content for.
     *
     * @return array<string, array{from: string, html: string, everyN: int|null}>
     */
    protected function found(): array
    {
        $found = [];

        foreach (self::SLOTS as $location => $slot) {
            $key = "davwheat-ads.ad-code.$location";
            $html = $this->settings->get($key);

            if (! is_string($html) || trim($html) === '') {
                continue;
            }

            $found[$slot] = [
                'from' => $key,
                'html' => trim($html),
                'everyN' => $slot === 'post_footer' ? $this->betweenPosts() : null,
            ];
        }

        return $found;
    }

    protected function betweenPosts(): ?int
    {
        $stored = $this->settings->get('davwheat-ads.between-n-posts');

        return is_numeric($stored) && (int) $stored > 0 ? (int) $stored : null;
    }

    /**
     * @param  array<string, array{from: string, html: string, everyN: int|null}>  $found
     */
    protected function write(array $found): void
    {
        $campaign = new Campaign();
        $campaign->forceFill([
            'name' => 'Imported from davwheat/flarum-ext-ads',
            // House, so it never competes with a paid campaign and never has a
            // cap to reach.
            'is_house' => true,
            'tier' => Campaign::TIER_HOUSE,
            // Draft, so nothing serves until somebody has looked at it.
            'status' => Campaign::STATUS_DRAFT,
            'pacing' => Campaign::PACING_ASAP,
            'frequency_window' => Campaign::WINDOW_DAY,
            'impressions' => 0,
            'clicks' => 0,
        ]);
        $campaign->save();

        foreach ($found as $slot => $advert) {
            $creative = new Creative();
            $creative->forceFill([
                'campaign_id' => $campaign->id,
                'name' => $advert['from'],
                'type' => 'raw_html',
                'status' => Creative::STATUS_DRAFT,
                'weight' => 10,
                'payload' => ['html' => $advert['html'], 'height' => 250, 'sandbox' => true],
                'impressions' => 0,
                'viewable_impressions' => 0,
                'clicks' => 0,
            ]);
            $creative->save();

            $assignment = new Assignment();
            $assignment->forceFill([
                'creative_id' => $creative->id,
                'placement_key' => $slot,
                'weight' => null,
                'enabled' => true,
            ]);
            $assignment->save();

            if ($advert['everyN'] !== null) {
                $this->carryEveryN($slot, $advert['everyN']);
            }
        }
    }

    /**
     * davwheat's "between N posts" becomes the slot's own repeat interval.
     */
    protected function carryEveryN(string $slot, int $everyN): void
    {
        $setting = PlacementSetting::query()->find($slot) ?? new PlacementSetting();

        $setting->forceFill(['key' => $slot, 'every_n' => $everyN]);
        $setting->save();
    }

    /**
     * Whoever was ad-free before stays ad-free.
     *
     * The one thing an upgrade must not silently change: a supporter who paid
     * to browse without advertising should not start seeing it because the
     * extension was replaced.
     */
    protected function carryPermission(): void
    {
        $groups = $this->db->table('group_permission')
            ->where('permission', 'davwheat-ads.bypass-ads')
            ->pluck('group_id');

        foreach ($groups as $groupId) {
            $already = $this->db->table('group_permission')
                ->where('group_id', $groupId)
                ->where('permission', Permissions::VIEW_WITHOUT_ADS)
                ->exists();

            if (! $already) {
                $this->db->table('group_permission')->insert([
                    'group_id' => $groupId,
                    'permission' => Permissions::VIEW_WITHOUT_ADS,
                ]);
            }
        }

        if (count($groups)) {
            $this->info('Carried ad-free browsing across for '.count($groups).' group(s).');
        }
    }
}
