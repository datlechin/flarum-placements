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

use Datlechin\Placements\Model\Advertiser;
use Datlechin\Placements\Model\Assignment;
use Datlechin\Placements\Model\Campaign;
use Datlechin\Placements\Model\CampaignRule;
use Datlechin\Placements\Model\Creative;
use Datlechin\Placements\Model\PlacementSetting;
use Datlechin\Placements\Support\Settings;
use Flarum\Console\AbstractCommand;
use Flarum\Settings\SettingsRepositoryInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * Writes the forum's advertising configuration out as JSON.
 *
 * For moving a set-up from staging to production, for keeping a copy of it
 * before a large change, and for handing an agency what is running without
 * giving them an account.
 *
 * Three things it leaves out, each for its own reason.
 *
 * Counts, because they describe one forum's traffic and would be wrong the
 * moment they landed anywhere else. The hourly stats table is not touched
 * either: it is the large table, and a configuration bundle should be
 * something somebody can read.
 *
 * Report tokens, because they are credentials. An advertiser's report link is
 * the whole of their authentication, and a file that carries one turns "here
 * is what we are running" into "here is a working login".
 *
 * Submissions, because they belong to this forum's members. An advertiser row
 * with a `user_id` points at an account that does not exist on the machine the
 * file is going to, and silently keeping the number would attach somebody
 * else's adverts to a stranger.
 */
class ExportCommand extends AbstractCommand
{
    /**
     * Bumped when the shape changes, so an importer can refuse a file it does
     * not understand rather than half-reading it.
     */
    public const FORMAT = 1;

    public function __construct(
        protected SettingsRepositoryInterface $settings,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('placements:export')
            ->setDescription("Write the forum's advertising configuration out as JSON")
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Write to this file rather than to standard output');
    }

    protected function fire(): int
    {
        $advertisers = $this->advertisers();
        $unattached = $this->unattachedCampaigns();

        $bundle = [
            'format' => self::FORMAT,
            'settings' => $this->settings(),
            'slots' => $this->slots(),
            'advertisers' => $advertisers,
            // House campaigns and anything else nobody is billed for. Walking
            // advertisers alone would drop these silently, and a house
            // campaign is one of the things most worth moving.
            'campaigns' => $unattached,
        ];

        // Pretty-printed and with slashes left alone: this is a file somebody
        // reads and puts in version control, not a wire format.
        $json = json_encode($bundle, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($json === false) {
            $this->error('The configuration could not be encoded as JSON.');

            return 1;
        }

        $output = $this->input->getOption('output');

        if (! is_string($output) || $output === '') {
            $this->output->writeln($json);

            return 0;
        }

        if (file_put_contents($output, $json.PHP_EOL) === false) {
            $this->error("Could not write to [$output].");

            return 1;
        }

        $campaigns = count($unattached);
        $creatives = 0;

        foreach ($unattached as $campaign) {
            $creatives += is_array($campaign['creatives'] ?? null) ? count($campaign['creatives']) : 0;
        }

        foreach ($advertisers as $advertiser) {
            $campaigns += count($advertiser['campaigns']);

            foreach ($advertiser['campaigns'] as $campaign) {
                $creatives += is_array($campaign['creatives'] ?? null) ? count($campaign['creatives']) : 0;
            }
        }

        $this->info(sprintf(
            'Wrote %d advertiser(s), %d campaign(s) and %d creative(s) to %s.',
            count($advertisers),
            $campaigns,
            $creatives,
            $output
        ));

        return 0;
    }

    /**
     * @return array<string, mixed>
     */
    protected function settings(): array
    {
        $exported = [];

        foreach (Settings::EXPORTABLE as $key) {
            $exported[$key] = $this->settings->get($key);
        }

        return $exported;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function slots(): array
    {
        $slots = [];

        /** @var PlacementSetting $slot */
        foreach (PlacementSetting::query()->orderBy('key')->get() as $slot) {
            $passback = $slot->passback;

            $slots[] = [
                'key' => $slot->key,
                'enabled' => $slot->enabled,
                'max_fill' => $slot->max_fill,
                'fallback' => $slot->fallback,
                'label_mode' => $slot->label_mode,
                'rotation' => $slot->rotation,
                'reserve_phone' => $slot->reserve_phone,
                'reserve_tablet' => $slot->reserve_tablet,
                'reserve_desktop' => $slot->reserve_desktop,
                'every_n' => $slot->every_n,
                'repeat_limit' => $slot->repeat_limit,
                'sort_order' => $slot->sort_order,
                // `passback_creative_id` is deliberately absent: it points at
                // a row whose id will not survive the trip. The importer
                // resolves this name back to an id on the other side.
                'passback' => $passback instanceof Creative ? $passback->name : null,
            ];
        }

        return $slots;
    }

    /**
     * @return list<array{name: string, contact_email: string|null, notes: string|null, campaigns: list<array<string, mixed>>}>
     */
    protected function advertisers(): array
    {
        $exported = [];

        $advertisers = Advertiser::query()
            // Member-submitted inventory belongs to this forum's accounts and
            // does not travel.
            ->whereNull('user_id')
            ->with(['campaigns.creatives.assignments', 'campaigns.rules'])
            ->orderBy('id')
            ->get();

        /** @var Advertiser $advertiser */
        foreach ($advertisers as $advertiser) {
            $campaigns = [];

            /** @var Campaign $campaign */
            foreach ($advertiser->campaigns as $campaign) {
                $campaigns[] = $this->campaign($campaign);
            }

            $exported[] = [
                'name' => $advertiser->name,
                'contact_email' => $advertiser->contact_email,
                'notes' => $advertiser->notes,
                'campaigns' => $campaigns,
            ];
        }

        return $exported;
    }

    /**
     * Campaigns nobody is billed for -- house adverts, and anything else set
     * up without an advertiser.
     *
     * @return list<array<string, mixed>>
     */
    protected function unattachedCampaigns(): array
    {
        $exported = [];

        $campaigns = Campaign::query()
            ->whereNull('advertiser_id')
            ->with(['creatives.assignments', 'rules'])
            ->orderBy('id')
            ->get();

        /** @var Campaign $campaign */
        foreach ($campaigns as $campaign) {
            $exported[] = $this->campaign($campaign);
        }

        return $exported;
    }

    /**
     * @return array<string, mixed>
     */
    protected function campaign(Campaign $campaign): array
    {
        return [
            'name' => $campaign->name,
            'status' => $campaign->status,
            'tier' => $campaign->tier,
            'is_house' => $campaign->is_house,
            'starts_at' => $campaign->starts_at?->toIso8601String(),
            'ends_at' => $campaign->ends_at?->toIso8601String(),
            'daypart_mask' => $campaign->daypart_mask,
            'max_impressions' => $campaign->max_impressions,
            'max_clicks' => $campaign->max_clicks,
            'pacing' => $campaign->pacing,
            'frequency_cap' => $campaign->frequency_cap,
            'frequency_window' => $campaign->frequency_window,
            'rate_type' => $campaign->rate_type,
            'rate_amount' => $campaign->rate_amount,
            'rate_currency' => $campaign->rate_currency,
            'contract_notes' => $campaign->contract_notes,
            'rules' => $this->rules($campaign),
            'creatives' => $this->creatives($campaign),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function rules(Campaign $campaign): array
    {
        $rules = [];

        /** @var CampaignRule $rule */
        foreach ($campaign->rules as $rule) {
            $rules[] = [
                'dimension' => $rule->dimension,
                'operator' => $rule->operator,
                'value' => $rule->value,
            ];
        }

        return $rules;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function creatives(Campaign $campaign): array
    {
        $creatives = [];

        /** @var Creative $creative */
        foreach ($campaign->creatives as $creative) {
            $assignments = [];

            /** @var Assignment $assignment */
            foreach ($creative->assignments as $assignment) {
                $assignments[] = [
                    'placement_key' => $assignment->placement_key,
                    'weight' => $assignment->weight,
                    'enabled' => $assignment->enabled,
                ];
            }

            $creatives[] = [
                'name' => $creative->name,
                'type' => $creative->type,
                'status' => $creative->status,
                'weight' => $creative->weight,
                'destination_url' => $creative->destination_url,
                'label_override' => $creative->label_override,
                'variant_group' => $creative->variant_group,
                'payload' => $creative->payload,
                'assignments' => $assignments,
            ];
        }

        return $creatives;
    }
}
