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
use Datlechin\Placements\Measurement\EventToken;
use Datlechin\Placements\PlacementRegistry;
use Datlechin\Placements\PlacementServiceProvider;
use Datlechin\Placements\Targeting\DimensionInterface;
use Datlechin\Placements\Targeting\RuleEvaluator;
use Datlechin\Placements\Targeting\TargetingContext;
use Illuminate\Contracts\Container\Container;

/**
 * Works out what this viewer is eligible to see, per slot.
 *
 * Everything here happens in memory against the cached plan, so a page view
 * costs no queries. What it does *not* do is choose: the browser makes the
 * final draw, because the remaining inputs only exist there.
 *
 * @phpstan-import-type PlanCampaign from PlanSource
 */
class PlanBuilder
{
    /**
     * @var list<DimensionInterface>|null
     */
    private ?array $dimensions = null;

    public function __construct(
        protected PlanSource $source,
        protected PlacementRegistry $registry,
        protected Container $container,
        protected ?EventToken $tokens = null,
        protected string $timezone = 'UTC',
    ) {
    }

    /**
     * Eligible creatives grouped by placement key, best tier first.
     *
     * Only slots with something in them appear, so a slot the client finds no
     * entry for renders nothing and reserves no space.
     *
     * @param  list<string>|null  $enabledKeys  Restrict to these slots; null means every registered one.
     * @return array<string, list<array<string, mixed>>>
     */
    public function forContext(TargetingContext $context, ?array $enabledKeys = null, ?Carbon $now = null): array
    {
        $now ??= Carbon::now();
        $viewer = $this->resolveViewer($context);
        $allowed = $enabledKeys === null ? null : array_flip($enabledKeys);

        $slots = [];

        foreach ($this->source->campaigns() as $campaign) {
            if (! Liveness::of($campaign, $now)) {
                continue;
            }

            // Forum-local hours, not the reader's: Flarum stores no timezone
            // for anybody, and "nine to five" is what an administrator means
            // when they draw the grid.
            if (! Daypart::allows($campaign['daypart_mask'] ?? null, $now, $this->timezone)) {
                continue;
            }

            if (! RuleEvaluator::matches($campaign['rules'], $viewer)) {
                continue;
            }

            // Last, because it is the only check that is not deterministic:
            // everything above either matches or does not, and rolling a die
            // before them would make the same page view answerable two ways.
            if (! Pacing::shouldServe($campaign, $now)) {
                continue;
            }

            $this->collect($campaign, $allowed, $slots);
        }

        foreach ($slots as $key => $candidates) {
            // Best tier first, so the client can take the leading run and
            // never has to think about tiers again.
            usort($candidates, fn (array $a, array $b) => $a['tier'] <=> $b['tier']);

            $slots[$key] = $candidates;
        }

        return $slots;
    }

    /**
     * @param  PlanCampaign  $campaign
     * @param  array<string, int>|null  $allowed
     * @param  array<string, list<array<string, mixed>>>  $slots
     */
    protected function collect(array $campaign, ?array $allowed, array &$slots): void
    {
        foreach ($campaign['creatives'] as $creative) {
            foreach ($creative['placements'] as $key => $weight) {
                // A slot the administrator switched off, or one whose owning
                // extension has been disabled since the assignment was made.
                // An orphaned assignment is inert rather than broken, which is
                // what we want.
                if (($allowed !== null && ! isset($allowed[$key])) || ! $this->registry->has($key)) {
                    continue;
                }

                $placement = $this->registry->getOrFail($key);

                if (! $placement->accepts($creative['type'])) {
                    continue;
                }

                $slots[$key][] = (new Candidate(
                    creativeId: $creative['id'],
                    campaignId: $campaign['id'],
                    tier: $campaign['tier'],
                    // An assignment may give a creative a different share in
                    // one slot; a weight of zero would silently remove it from
                    // the draw, which is what "disabled" is for.
                    weight: max(1, $weight ?? $creative['weight']),
                    type: $creative['type'],
                    payload: $creative['payload'],
                    url: $creative['url'],
                    label: $creative['label'],
                    // Issued per decision, so a token is worth exactly one
                    // impression in one slot and cannot be moved to another
                    // creative to burn somebody else's cap.
                    token: $this->tokens?->issue((int) $creative['id'], (int) $campaign['id'], $key),
                    frequencyCap: $campaign['frequency_cap'],
                    frequencyWindow: $campaign['frequency_window'],
                ))->toArray();
            }
        }
    }

    /**
     * This viewer's value on every server-side axis, resolved once.
     *
     * Once per request rather than once per campaign: a forum with fifty
     * campaigns would otherwise read the actor's groups fifty times.
     *
     * @return array<string, list<string>|string|int|null>
     */
    public function resolveViewer(TargetingContext $context): array
    {
        $viewer = [];

        foreach ($this->dimensions() as $dimension) {
            if ($dimension->isServerSide()) {
                $viewer[$dimension->key()] = $dimension->resolve($context);
            }
        }

        return $viewer;
    }

    /**
     * @return list<DimensionInterface>
     */
    protected function dimensions(): array
    {
        if ($this->dimensions === null) {
            /** @var list<class-string<DimensionInterface>> $classes */
            $classes = $this->container->make(PlacementServiceProvider::DIMENSIONS);

            $this->dimensions = array_map(
                fn (string $class) => $this->container->make($class),
                $classes
            );
        }

        return $this->dimensions;
    }
}
