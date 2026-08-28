<?php

/*
 * This file is part of datlechin/flarum-placement.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\Placement\Console;

use Datlechin\Placement\Creative\CreativeTypeRegistry;
use Datlechin\Placement\Model\Advertiser;
use Datlechin\Placement\Model\Assignment;
use Datlechin\Placement\Model\Campaign;
use Datlechin\Placement\Model\CampaignRule;
use Datlechin\Placement\Model\Creative;
use Datlechin\Placement\Model\PlacementSetting;
use Datlechin\Placement\PlacementRegistry;
use Datlechin\Placement\Support\Settings;
use Flarum\Console\AbstractCommand;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Contracts\Validation\Factory as Validation;
use Illuminate\Database\ConnectionInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

/**
 * Reads back what `placement:export` wrote.
 *
 * Adds to what is already there rather than replacing it, and never matches on
 * an id: the file carries none, because an id from another database means
 * nothing here. Advertisers and campaigns are matched by name, so importing
 * the same file twice updates rather than duplicating.
 *
 * Everything arrives paused. A configuration bundle is somebody else's
 * decisions about somebody else's forum, and the last thing an import should
 * do is put adverts on the page before anybody has looked at the result --
 * particularly since a creative's approval travels with it, so the usual
 * review gate is already spent.
 *
 * Slot assignments naming a placement this forum does not have are dropped and
 * reported, rather than stored to point at nothing. A creative whose payload
 * no longer matches its type is skipped the same way: the alternative is a row
 * that the renderer is the first thing to find out about.
 */
class ImportCommand extends AbstractCommand
{
    public function __construct(
        protected SettingsRepositoryInterface $settings,
        protected ConnectionInterface $db,
        protected PlacementRegistry $registry,
        protected CreativeTypeRegistry $types,
        // Injected rather than reached through a global `validator()` helper:
        // Flarum is not a Laravel application and does not define one.
        protected Validation $validation,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('placement:import')
            ->setDescription('Read an advertising configuration written by placement:export')
            ->addArgument('file', InputArgument::REQUIRED, 'The JSON file to read')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report what would be imported without writing anything')
            ->addOption('settings', null, InputOption::VALUE_NONE, "Bring the forum's own settings across as well");
    }

    protected function fire(): int
    {
        $bundle = $this->read();

        if ($bundle === null) {
            return 1;
        }

        $dryRun = (bool) $this->input->getOption('dry-run');

        // One transaction, so a file that turns out to be broken half way
        // through leaves nothing behind. A dry run rolls back at the end
        // rather than branching everywhere: it then exercises exactly the
        // code the real run does, which is the only way its report is worth
        // anything.
        $this->db->beginTransaction();

        try {
            $counts = $this->apply($bundle);
        } catch (\Throwable $e) {
            $this->db->rollBack();
            $this->error('Nothing was imported: '.$e->getMessage());

            return 1;
        }

        if ($dryRun) {
            $this->db->rollBack();
        } else {
            $this->db->commit();
        }

        $this->info(sprintf(
            '%s %d advertiser(s), %d campaign(s), %d creative(s) and %d slot setting(s).',
            $dryRun ? 'Would import' : 'Imported',
            $counts['advertisers'],
            $counts['campaigns'],
            $counts['creatives'],
            $counts['slots']
        ));

        if ($counts['creatives'] > 0 && ! $dryRun) {
            $this->info('Every campaign is paused. Nothing will appear on the forum until you activate them.');
        }

        return 0;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function read(): ?array
    {
        $path = $this->input->getArgument('file');

        if (! is_string($path) || ! is_file($path) || ! is_readable($path)) {
            $path = is_string($path) ? $path : '';
            $this->error("Cannot read [$path].");

            return null;
        }

        $bundle = json_decode((string) file_get_contents($path), true);

        if (! is_array($bundle)) {
            $this->error("[$path] is not valid JSON.");

            return null;
        }

        // Refused rather than half-read. A file from a later version may hold
        // fields this build would silently drop.
        if (($bundle['format'] ?? null) !== ExportCommand::FORMAT) {
            $this->error(sprintf(
                'This file is format %s; this version reads format %d.',
                var_export($bundle['format'] ?? null, true),
                ExportCommand::FORMAT
            ));

            return null;
        }

        return $bundle;
    }

    /**
     * @param  array<string, mixed>  $bundle
     * @return array{advertisers: int, campaigns: int, creatives: int, slots: int}
     */
    protected function apply(array $bundle): array
    {
        $counts = ['advertisers' => 0, 'campaigns' => 0, 'creatives' => 0, 'slots' => 0];

        if ($this->input->getOption('settings')) {
            $this->applySettings($bundle['settings'] ?? []);
        }

        foreach ($this->list($bundle, 'advertisers') as $row) {
            $advertiser = $this->advertiser($row);
            $counts['advertisers']++;

            foreach ($this->list($row, 'campaigns') as $campaignRow) {
                $campaign = $this->campaign($advertiser, $campaignRow);
                $counts['campaigns']++;

                foreach ($this->list($campaignRow, 'creatives') as $creativeRow) {
                    if ($this->creative($campaign, $creativeRow)) {
                        $counts['creatives']++;
                    }
                }
            }
        }

        // House campaigns and anything else nobody is billed for.
        foreach ($this->list($bundle, 'campaigns') as $campaignRow) {
            $campaign = $this->campaign(null, $campaignRow);
            $counts['campaigns']++;

            foreach ($this->list($campaignRow, 'creatives') as $creativeRow) {
                if ($this->creative($campaign, $creativeRow)) {
                    $counts['creatives']++;
                }
            }
        }

        // After the creatives, because a slot's passback names one.
        foreach ($this->list($bundle, 'slots') as $row) {
            if ($this->slot($row)) {
                $counts['slots']++;
            }
        }

        return $counts;
    }

    /**
     * @param  array<mixed, mixed>  $row
     * @return list<array<mixed, mixed>>
     */
    protected function list(array $row, string $key): array
    {
        $value = $row[$key] ?? null;

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, 'is_array'));
    }

    /**
     * @param  mixed  $settings
     */
    protected function applySettings($settings): void
    {
        if (! is_array($settings)) {
            return;
        }

        // Only the keys the exporter names. A file is a text file somebody may
        // have edited, and a loop over whatever it happens to contain would
        // let it write any setting on the forum.
        foreach (Settings::EXPORTABLE as $key) {
            if (array_key_exists($key, $settings) && is_scalar($settings[$key])) {
                $this->settings->set($key, (string) $settings[$key]);
            }
        }
    }

    /**
     * @param  array<mixed, mixed>  $row
     */
    protected function advertiser(array $row): Advertiser
    {
        $name = $this->string($row, 'name') ?? 'Imported advertiser';

        // Matched by name and never by id, and never against an advertiser
        // belonging to a member: those are somebody's own submissions and a
        // file has no business joining onto them.
        $advertiser = Advertiser::query()->whereNull('user_id')->where('name', $name)->first();

        if (! $advertiser instanceof Advertiser) {
            $advertiser = new Advertiser();
        }

        $advertiser->forceFill([
            'name' => $name,
            'contact_email' => $this->string($row, 'contact_email'),
            'notes' => $this->string($row, 'notes'),
            // Never carried in a file, and not invented here either: a report
            // link is issued by whoever is running this forum.
            'user_id' => null,
        ]);
        $advertiser->save();

        return $advertiser;
    }

    /**
     * @param  Advertiser|null  $advertiser  Null for a house campaign, or
     *                                       anything else nobody is billed for.
     * @param  array<mixed, mixed>  $row
     */
    protected function campaign(?Advertiser $advertiser, array $row): Campaign
    {
        $name = $this->string($row, 'name') ?? 'Imported campaign';

        // Matched within the advertiser it belongs to, so two advertisers may
        // each have a campaign called "Q1" without one overwriting the other.
        $campaign = $advertiser instanceof Advertiser
            ? $advertiser->campaigns()->where('name', $name)->first()
            : Campaign::query()->whereNull('advertiser_id')->where('name', $name)->first();

        if (! $campaign instanceof Campaign) {
            $campaign = new Campaign();
        }

        $campaign->forceFill([
            'advertiser_id' => $advertiser?->id,
            'name' => $name,
            // Paused whatever the file said. See the class docblock.
            'status' => Campaign::STATUS_PAUSED,
            'tier' => $this->int($row, 'tier') ?? Campaign::TIER_STANDARD,
            'is_house' => ($row['is_house'] ?? false) === true,
            'starts_at' => $this->string($row, 'starts_at'),
            'ends_at' => $this->string($row, 'ends_at'),
            'daypart_mask' => $this->string($row, 'daypart_mask'),
            'max_impressions' => $this->int($row, 'max_impressions'),
            'max_clicks' => $this->int($row, 'max_clicks'),
            'pacing' => $this->string($row, 'pacing') ?? Campaign::PACING_ASAP,
            'frequency_cap' => $this->int($row, 'frequency_cap'),
            'frequency_window' => $this->string($row, 'frequency_window') ?? Campaign::WINDOW_DAY,
            'rate_type' => $this->string($row, 'rate_type'),
            'rate_amount' => $this->string($row, 'rate_amount'),
            'rate_currency' => $this->string($row, 'rate_currency'),
            'contract_notes' => $this->string($row, 'contract_notes'),
            // Counts belong to the forum that served them.
            'impressions' => 0,
            'clicks' => 0,
        ]);
        $campaign->save();

        $campaign->rules()->delete();

        foreach ($this->list($row, 'rules') as $ruleRow) {
            $dimension = $this->string($ruleRow, 'dimension');
            $operator = $this->string($ruleRow, 'operator');

            if ($dimension === null || $operator === null) {
                continue;
            }

            $rule = new CampaignRule();
            $rule->forceFill([
                'dimension' => $dimension,
                'operator' => $operator,
                'value' => $this->string($ruleRow, 'value') ?? '',
            ]);

            $campaign->rules()->save($rule);
        }

        return $campaign;
    }

    /**
     * @param  array<mixed, mixed>  $row
     * @return bool Whether it was imported.
     */
    protected function creative(Campaign $campaign, array $row): bool
    {
        $name = $this->string($row, 'name') ?? 'Imported creative';
        $typeKey = $this->string($row, 'type') ?? '';
        $type = $this->types->get($typeKey);

        if ($type === null) {
            $this->error("Skipped creative [$name]: this forum has no creative type named [$typeKey].");

            return false;
        }

        $payload = $type->normalize(is_array($row['payload'] ?? null) ? $row['payload'] : []);

        // Validated on the way in, exactly as it would be through the API. A
        // file is not more trusted than a form, and a payload the renderer
        // cannot draw is a slot that silently shows nothing.
        try {
            $rules = $type->rules();

            if ($rules !== []) {
                $this->validation->make($payload, $rules)->validate();
            }
        } catch (\Throwable $e) {
            $this->error("Skipped creative [$name]: its payload is not valid for type [$typeKey].");

            return false;
        }

        $creative = $campaign->creatives()->where('name', $name)->first();

        if (! $creative instanceof Creative) {
            $creative = new Creative();
        }

        $creative->forceFill([
            'campaign_id' => $campaign->id,
            'name' => $name,
            'type' => $typeKey,
            // Kept. The alternative is putting every creative in a restored
            // backup back through review, which is a day's work to undo a
            // restore.
            'status' => $this->string($row, 'status') ?? Creative::STATUS_DRAFT,
            'weight' => $this->int($row, 'weight') ?? Creative::MIN_WEIGHT,
            'destination_url' => $this->destination($row),
            'label_override' => $this->string($row, 'label_override'),
            'variant_group' => $this->string($row, 'variant_group'),
            'payload' => $payload,
            'impressions' => 0,
            'viewable_impressions' => 0,
            'clicks' => 0,
        ]);
        $creative->save();

        $creative->assignments()->delete();

        foreach ($this->list($row, 'assignments') as $assignmentRow) {
            $key = $this->string($assignmentRow, 'placement_key');

            if ($key === null) {
                continue;
            }

            if (! $this->registry->has($key)) {
                $this->error("Creative [$name] is assigned to slot [$key], which this forum does not have. Dropped.");

                continue;
            }

            $assignment = new Assignment();
            $assignment->forceFill([
                'placement_key' => $key,
                'weight' => $this->int($assignmentRow, 'weight'),
                'enabled' => ($assignmentRow['enabled'] ?? true) !== false,
            ]);

            $creative->assignments()->save($assignment);
        }

        return true;
    }

    /**
     * A destination is only kept when it is one this extension would have
     * accepted through the API. A `javascript:` address in a file somebody
     * edited is the same script execution it would be anywhere else.
     *
     * @param  array<mixed, mixed>  $row
     */
    protected function destination(array $row): ?string
    {
        $url = $this->string($row, 'destination_url');

        return $url !== null && Creative::isAllowedDestination($url) ? $url : null;
    }

    /**
     * @param  array<mixed, mixed>  $row
     * @return bool Whether it was imported.
     */
    protected function slot(array $row): bool
    {
        $key = $this->string($row, 'key');

        if ($key === null || ! $this->registry->has($key)) {
            if ($key !== null) {
                $this->error("Skipped slot [$key], which this forum does not have.");
            }

            return false;
        }

        $setting = PlacementSetting::query()->where('key', $key)->first();

        if (! $setting instanceof PlacementSetting) {
            $setting = new PlacementSetting();
        }

        $passback = $this->string($row, 'passback');

        $setting->forceFill([
            'key' => $key,
            'enabled' => ($row['enabled'] ?? true) !== false,
            'max_fill' => $this->int($row, 'max_fill') ?? 1,
            'fallback' => $this->string($row, 'fallback') ?? PlacementSetting::FALLBACK_HOUSE,
            'label_mode' => $this->string($row, 'label_mode') ?? PlacementSetting::LABEL_INHERIT,
            'rotation' => $this->string($row, 'rotation') ?? PlacementSetting::ROTATION_RANDOM,
            'reserve_phone' => $this->int($row, 'reserve_phone'),
            'reserve_tablet' => $this->int($row, 'reserve_tablet'),
            'reserve_desktop' => $this->int($row, 'reserve_desktop'),
            'every_n' => $this->int($row, 'every_n'),
            'repeat_limit' => $this->int($row, 'repeat_limit'),
            'sort_order' => $this->int($row, 'sort_order') ?? 0,
            // Resolved by name, because the id it had in the other database
            // means nothing here. A passback naming a creative that did not
            // come across leaves the slot with none rather than pointing it at
            // whatever happens to hold that id.
            'passback_creative_id' => $passback === null ? null : Creative::query()->where('name', $passback)->value('id'),
        ]);
        $setting->save();

        return true;
    }

    /**
     * @param  array<mixed, mixed>  $row
     */
    protected function string(array $row, string $key): ?string
    {
        $value = $row[$key] ?? null;

        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @param  array<mixed, mixed>  $row
     */
    protected function int(array $row, string $key): ?int
    {
        $value = $row[$key] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }
}
