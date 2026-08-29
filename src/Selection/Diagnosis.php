<?php

/*
 * This file is part of datlechin/flarum-placements.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\Placements\Selection;

use Carbon\Carbon;
use Datlechin\Placements\Model\Campaign;
use Datlechin\Placements\Model\Creative;
use Datlechin\Placements\Model\PlacementSetting;
use Datlechin\Placements\PlacementRegistry;
use Datlechin\Placements\Support\Settings;
use Datlechin\Placements\Targeting\RuleEvaluator;
use Datlechin\Placements\Targeting\TargetingContext;
use Flarum\Settings\SettingsRepositoryInterface;

/**
 * Why one creative is not being served in one slot.
 *
 * "Why is my advert not showing?" is most of the support an ad server ever
 * generates, and every gate that could answer it was a bare `continue`.
 * `RuleEvaluator::firstFailure()` was written for exactly this, documented as
 * such, and called only by `matches()`, which threw the answer away.
 *
 * The gates are asked in the same order the serving path asks them, so the
 * first refusal reported here is the first refusal that happens. It is
 * deliberately not a re-implementation: the campaign row comes from
 * `PlanSource`, which is the same cache the plan is built from, so a
 * diagnosis cannot disagree with delivery about what the data says.
 *
 * The answer is always "to this viewer, in this slot, at this moment". There
 * is no other honest answer: targeting depends on who is asking, and dayparting
 * and pacing depend on when.
 *
 * @phpstan-import-type PlanCampaign from PlanSource
 */
class Diagnosis
{
    public const ELIGIBLE = 'eligible';

    public function __construct(
        protected PlanSource $source,
        protected PlacementRegistry $registry,
        protected PlanBuilder $builder,
        protected SettingsRepositoryInterface $settings,
    ) {
    }

    /**
     * @return array{reason: string, dimension?: string}
     */
    public function of(Creative $creative, string $placementKey, TargetingContext $context, ?Carbon $now = null): array
    {
        $now ??= Carbon::now();

        if (! $this->registry->has($placementKey)) {
            return ['reason' => 'slot_unknown'];
        }

        $placement = $this->registry->getOrFail($placementKey);

        // Read from the row rather than through `resolve()`: an absent row
        // means default-configured, and the default is enabled.
        $setting = PlacementSetting::query()->find($placementKey);

        if ($setting !== null && ! $setting->enabled) {
            return ['reason' => 'slot_disabled'];
        }

        if (! array_key_exists($placementKey, $this->assignments($creative))) {
            return ['reason' => 'not_assigned'];
        }

        if (! $placement->accepts($creative->type)) {
            return ['reason' => 'type_not_accepted'];
        }

        if ($creative->status !== Creative::STATUS_APPROVED) {
            return ['reason' => 'not_approved'];
        }

        $campaign = $creative->campaign;

        if ($campaign === null) {
            return ['reason' => 'no_campaign'];
        }

        if (in_array($campaign->status, [Campaign::STATUS_DRAFT, Campaign::STATUS_ARCHIVED], true)) {
            return ['reason' => 'campaign_not_running'];
        }

        $row = $this->planRow((int) $campaign->id);

        if ($row === null) {
            // The plan is built from a cache that a save invalidates, so this
            // is a campaign whose row has not been rebuilt yet rather than one
            // that is wrong.
            return ['reason' => 'not_in_plan'];
        }

        if (! Liveness::of($row, $now)) {
            return ['reason' => Liveness::capped($row) ? 'campaign_capped' : 'campaign_not_live'];
        }

        if (! Daypart::allows($row['daypart_mask'] ?? null, $now, Settings::timezone($this->settings))) {
            return ['reason' => 'outside_daypart'];
        }

        $failed = RuleEvaluator::firstFailure($row['rules'], $this->builder->resolveViewer($context));

        if ($failed !== null) {
            return ['reason' => 'targeted_out', 'dimension' => $failed];
        }

        // Reported but not treated as a refusal: pacing is the one gate that
        // rolls a die, so "it did not serve this time" is not the same as "it
        // will not serve", and saying otherwise would send somebody looking
        // for a fault that is not there.
        if (! Pacing::shouldServe($row, $now)) {
            return ['reason' => 'paced'];
        }

        return ['reason' => self::ELIGIBLE];
    }

    /**
     * The slots this creative is assigned to, as the plan sees them.
     *
     * @return array<string, int|null>
     */
    protected function assignments(Creative $creative): array
    {
        $map = [];

        foreach ($creative->assignments as $assignment) {
            if ($assignment->enabled) {
                $map[$assignment->placement_key] = $assignment->weight;
            }
        }

        return $map;
    }

    /**
     * @return PlanCampaign|null
     */
    protected function planRow(int $campaignId): ?array
    {
        foreach ($this->source->campaigns() as $campaign) {
            if ($campaign['id'] === $campaignId) {
                return $campaign;
            }
        }

        return null;
    }
}
