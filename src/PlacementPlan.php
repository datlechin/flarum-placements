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

use Datlechin\Placements\Model\Creative;
use Datlechin\Placements\Model\PlacementSetting;
use Datlechin\Placements\Selection\Candidate;
use Datlechin\Placements\Selection\PlanBuilder;
use Datlechin\Placements\Support\DemoMode;
use Datlechin\Placements\Support\Permissions;
use Datlechin\Placements\Targeting\TargetingContext;
use Flarum\User\User;
use Illuminate\Contracts\Cache\Repository as Cache;
use Jaybizzle\CrawlerDetect\CrawlerDetect;
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

        $passbacks = $this->passbacks($slots);

        $filled = [];

        foreach ($slots as $key => $config) {
            $forSlot = $candidates[$key] ?? [];

            // Appended after the eligible inventory and never sorted into it,
            // so the client can tell the two apart: a passback is what a slot
            // shows when nothing else could, not something competing to be
            // drawn.
            if (isset($passbacks[$key])) {
                $forSlot[] = $passbacks[$key];
            }

            // A slot with nothing eligible is left out entirely rather than
            // sent empty: it saves the bytes on the critical path, and the
            // client renders nothing and reserves no space for a key it cannot
            // find. Checked after the passback, or a slot whose only content
            // is its fallback would never be sent.
            if ($forSlot === []) {
                continue;
            }

            $filled[$key] = $config + ['candidates' => $forSlot];
        }

        return $filled === [] ? null : ['demo' => false, 'slots' => $filled];
    }

    /**
     * The nominated fallback creative for each slot set to use one.
     *
     * Loaded in one query for every such slot, and only for creatives that
     * have been approved -- a passback is still an advert on the forum, and
     * nominating one is not a way around review.
     *
     * Deliberately not put through targeting or pacing. It is the answer to
     * "nothing matched", so a rule that stopped it from matching would leave
     * the slot with nothing, which is what the setting exists to avoid.
     *
     * @param  array<string, array<string, mixed>>  $slots
     * @return array<string, array<string, mixed>>
     */
    protected function passbacks(array $slots): array
    {
        $wanted = [];

        foreach ($slots as $key => $config) {
            $id = $config['passbackCreativeId'] ?? null;

            if (($config['fallback'] ?? null) === PlacementSetting::FALLBACK_PASSBACK && is_numeric($id)) {
                $wanted[$key] = (int) $id;
            }
        }

        if ($wanted === []) {
            return [];
        }

        $creatives = Creative::query()
            ->whereIn('id', array_values(array_unique($wanted)))
            ->where('status', Creative::STATUS_APPROVED)
            ->get()
            ->keyBy('id');

        $found = [];

        foreach ($wanted as $key => $id) {
            /** @var Creative|null $creative */
            $creative = $creatives->get($id);

            if ($creative === null) {
                continue;
            }

            $found[$key] = (new Candidate(
                creativeId: (int) $creative->id,
                campaignId: (int) $creative->campaign_id,
                // The lowest priority there is: nothing should ever be drawn
                // in preference to real inventory because of a tier number.
                tier: PHP_INT_MAX,
                weight: 1,
                type: $creative->type,
                payload: $creative->payload,
                url: $creative->destination_url,
                label: $creative->label_override,
                passback: true,
            ))->toArray();
        }

        return $found;
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

    /**
     * `jaybizzle/crawler-detect` rather than `jenssegers/agent`, which is what
     * this used to ask and is only a wrapper around it: the wrapper is
     * archived, still declares `php >= 5.6`, and brings Mobile-Detect along
     * for device detection this extension does not do on the server. One
     * question, one dependency.
     */
    protected function isRobot(ServerRequestInterface $request): bool
    {
        $userAgent = $request->getHeaderLine('User-Agent');

        if ($userAgent === '') {
            return true;
        }

        return (new CrawlerDetect())->isCrawler($userAgent);
    }
}
