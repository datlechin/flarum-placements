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

use Datlechin\Placements\Model\Campaign;
use Datlechin\Placements\Model\CampaignRule;
use Datlechin\Placements\Model\Creative;
use Illuminate\Contracts\Cache\Repository as Cache;

/**
 * Everything sellable on the forum, as one cached array.
 *
 * @phpstan-type PlanRule array{dimension: string, operator: string, value: string}
 * @phpstan-type PlanCreative array{
 *     id: int,
 *     type: string,
 *     weight: int,
 *     url: string|null,
 *     label: string|null,
 *     payload: array<string, mixed>,
 *     placements: array<string, int|null>
 * }
 * @phpstan-type PlanCampaign array{
 *     id: int,
 *     tier: int,
 *     is_house: bool,
 *     status: string,
 *     starts_at: string|null,
 *     ends_at: string|null,
 *     daypart_mask: string|null,
 *     pacing: string,
 *     frequency_cap: int|null,
 *     frequency_window: string,
 *     max_impressions: int|null,
 *     max_clicks: int|null,
 *     impressions: int,
 *     clicks: int,
 *     rules: list<PlanRule>,
 *     creatives: list<PlanCreative>
 * }
 *
 * This is what makes serving cost no queries at all. A forum has on the order
 * of a hundred creatives, so the whole thing is a few kilobytes, and it only
 * changes when somebody edits a campaign — at which point the models drop it.
 *
 * The array holds the *structure*, not a decision: which campaigns exist, what
 * they target, what they can run, and their running counters. Whether a
 * campaign is live is worked out per request from the dates and the caps, so a
 * campaign expires on time on a forum whose cron has never run.
 *
 * The counters are cached with the rest, which means a cap is enforced as of
 * the last write rather than to the impression. Measurement flushes at most
 * once a minute, so overdelivery is bounded by that and by nothing else.
 */
class PlanSource
{
    public const CACHE_KEY = 'datlechin-placements.plan';

    public function __construct(protected Cache $cache)
    {
    }

    /**
     * @return list<PlanCampaign>
     */
    public function campaigns(): array
    {
        // The cache is a serialisation boundary, so the shape is asserted here
        // rather than inferred. `build()` below is the only thing that ever
        // writes it.
        /** @var list<PlanCampaign> */
        return $this->cache->rememberForever(self::CACHE_KEY, fn () => $this->build());
    }

    public function flush(): void
    {
        $this->cache->forget(self::CACHE_KEY);
    }

    /**
     * Three queries, run only when the cache is cold.
     *
     * Loaded rather than eager-loaded through relations because the result is
     * an array, not models: nothing downstream wants Eloquent objects, and
     * hydrating a few hundred of them per cache miss is pure waste.
     *
     * @return list<PlanCampaign>
     */
    protected function build(): array
    {
        $campaigns = Campaign::query()
            ->whereNotIn('status', [Campaign::STATUS_DRAFT, Campaign::STATUS_ARCHIVED])
            ->get()
            ->keyBy('id');

        if ($campaigns->isEmpty()) {
            return [];
        }

        $ids = $campaigns->keys()->all();

        $rules = CampaignRule::query()
            ->whereIn('campaign_id', $ids)
            ->get()
            ->groupBy('campaign_id');

        $creatives = Creative::query()
            ->whereIn('campaign_id', $ids)
            ->where('status', Creative::STATUS_APPROVED)
            ->with('assignments')
            ->get()
            ->groupBy('campaign_id');

        $built = [];

        foreach ($campaigns as $id => $campaign) {
            /** @var Campaign $campaign */
            $own = $creatives->get($id);

            // A campaign with nothing approved to show is not inventory.
            if ($own === null || $own->isEmpty()) {
                continue;
            }

            $built[] = [
                'id' => (int) $campaign->id,
                'tier' => $campaign->tier,
                'is_house' => $campaign->is_house,
                'status' => $campaign->status,
                'starts_at' => $campaign->starts_at?->toIso8601String(),
                'ends_at' => $campaign->ends_at?->toIso8601String(),
                'daypart_mask' => $campaign->daypart_mask,
                'pacing' => $campaign->pacing,
                'frequency_cap' => $campaign->frequency_cap,
                'frequency_window' => $campaign->frequency_window,
                'max_impressions' => $campaign->max_impressions,
                'max_clicks' => $campaign->max_clicks,
                'impressions' => $campaign->impressions,
                'clicks' => $campaign->clicks,
                'rules' => array_map(
                    fn (CampaignRule $rule) => [
                        'dimension' => $rule->dimension,
                        'operator' => $rule->operator,
                        'value' => $rule->value,
                    ],
                    array_values($rules->get($id)?->all() ?? [])
                ),
                'creatives' => array_map($this->creative(...), array_values($own->all())),
            ];
        }

        return $built;
    }

    /**
     * @return PlanCreative
     */
    protected function creative(Creative $creative): array
    {
        $placements = [];

        foreach ($creative->assignments as $assignment) {
            if ($assignment->enabled) {
                $placements[$assignment->placement_key] = $assignment->weight;
            }
        }

        return [
            'id' => (int) $creative->id,
            'type' => $creative->type,
            'weight' => $creative->weight,
            'url' => $creative->destination_url,
            'label' => $creative->label_override,
            'payload' => $creative->payload,
            'placements' => $placements,
        ];
    }
}
