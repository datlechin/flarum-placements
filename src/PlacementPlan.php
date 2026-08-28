<?php

/*
 * This file is part of datlechin/flarum-placements.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\Placements;

use Datlechin\Placements\Model\PlacementSetting;
use Datlechin\Placements\Selection\PlanBuilder;
use Datlechin\Placements\Support\DemoMode;
use Datlechin\Placements\Support\Permissions;
use Datlechin\Placements\Targeting\TargetingContext;
use Flarum\User\User;
use Illuminate\Contracts\Cache\Repository as Cache;
use Jenssegers\Agent\Agent;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Builds what the frontend is told about placements on this request.
 *
 * Two things make this cheap enough to run on every page view. The slot
 * configuration is one cached array, invalidated when an administrator changes
 * something, so serving costs no queries. And a viewer who will not be shown
 * anything gets no payload at all rather than an empty one: no reserved space,
 * no beacon, no bytes on the critical path, and nothing in view-source
 * describing inventory to somebody who cannot see it.
 */
class PlacementPlan
{
    public const CACHE_KEY = 'datlechin-placements.slots';

    public function __construct(
        protected PlacementRegistry $registry,
        protected Cache $cache,
        protected PlanBuilder $builder,
    ) {
    }

    /**
     * The payload for this viewer, or null when there is nothing to say.
     *
     * @param  array<string, mixed>|null  $apiDocument  The page's own document, which some dimensions read the current tags out of.
     * @return array{demo: bool, slots: array<string, array<string, mixed>>}|null
     */
    public function forActor(ServerRequestInterface $request, User $actor, ?array $apiDocument = null): ?array
    {
        $demo = DemoMode::forRequest($request, $actor);

        // Bots are told nothing. It costs them nothing to render and, more to
        // the point, it stops the extension manufacturing impressions on a
        // publisher's behalf: Flarum's SEO body sits inside <noscript>, so a
        // plain crawler sees no slots, but Googlebot renders the whole SPA and
        // would fire every beacon on the page.
        if (! $demo && $this->isRobot($request)) {
            return null;
        }

        // Demo mode outranks being ad-free, because its entire purpose is to
        // show the slots to the person checking that they work — who is
        // usually the same person who granted their own group ad-free
        // browsing.
        if (! $demo && Permissions::isAdFree($actor)) {
            return null;
        }

        $slots = $this->slots();

        // Demo mode wants every slot, filled with a sample, so that an
        // administrator can find the ones that have nothing in them — which is
        // most of the reason to turn it on.
        if ($demo) {
            return ['demo' => true, 'slots' => $slots];
        }

        $context = new TargetingContext($request, $actor, $apiDocument);
        $candidates = $this->builder->forContext($context, array_keys($slots));

        $filled = [];

        foreach ($slots as $key => $config) {
            // A slot with nothing eligible is left out entirely rather than
            // sent empty: it saves the bytes on the critical path, and the
            // client renders nothing and reserves no space for a key it cannot
            // find.
            if (($candidates[$key] ?? []) === []) {
                continue;
            }

            $filled[$key] = $config + ['candidates' => $candidates[$key]];
        }

        return $filled === [] ? null : ['demo' => false, 'slots' => $filled];
    }

    /**
     * Every enabled placement with its effective configuration, merged from
     * what the code declares and what an administrator overrode.
     *
     * @return array<string, array<string, mixed>>
     */
    public function slots(): array
    {
        /** @var array<string, array<string, mixed>> */
        return $this->cache->rememberForever(self::CACHE_KEY, fn () => $this->buildSlots());
    }

    /**
     * Drop the cached configuration. Called whenever a placement's settings
     * change; there is no version counter because there is nothing here worth
     * keeping an old copy of.
     */
    public function flush(): void
    {
        $this->cache->forget(self::CACHE_KEY);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function buildSlots(): array
    {
        $overrides = PlacementSetting::query()->get()->keyBy('key');

        $slots = [];

        foreach ($this->registry->all() as $key => $placement) {
            /** @var PlacementSetting|null $override */
            $override = $overrides->get($key);

            $resolved = PlacementSetting::resolve($placement, $override);

            if (! $resolved['enabled']) {
                continue;
            }

            $slots[$key] = $placement->toArray() + $resolved;
        }

        uasort($slots, fn (array $a, array $b) => $a['sortOrder'] <=> $b['sortOrder']);

        return $slots;
    }

    protected function isRobot(ServerRequestInterface $request): bool
    {
        $userAgent = $request->getHeaderLine('User-Agent');

        if ($userAgent === '') {
            return true;
        }

        return (new Agent())->isRobot($userAgent);
    }
}
