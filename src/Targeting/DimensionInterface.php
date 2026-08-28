<?php

/*
 * This file is part of datlechin/flarum-placements.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\Placements\Targeting;

/**
 * One axis a campaign can be targeted along: a tag, a group, a route, a
 * device, a country.
 *
 * This is the seam that matters most for anyone extending this extension. Geo,
 * referrer, day of week, subscription tier and everything else nobody has
 * thought of yet can live in somebody else's repository rather than in this
 * one's backlog, and none of it requires touching the eligibility evaluator.
 *
 * Rules combine as AND across dimensions and OR within one, and an `is_not`
 * always wins over an `is`. A dimension with no rules on a campaign does not
 * constrain it.
 *
 * Implementations are resolved from the container, so they may type-hint what
 * they need. `resolve()` runs on the serving path of every page view, so it
 * must not query.
 */
interface DimensionInterface
{
    public const IS = 'is';
    public const IS_NOT = 'is_not';
    public const GTE = 'gte';
    public const LTE = 'lte';

    /**
     * Stable identifier, stored in `campaign_rules.dimension`.
     */
    public function key(): string;

    /**
     * Translation key for the name shown in the rule editor.
     */
    public function label(): string;

    /**
     * Which operators make sense for this axis.
     *
     * Set membership takes `IS`/`IS_NOT`; a quantity such as post count takes
     * `GTE`/`LTE`, which are single-valued.
     *
     * @return list<string>
     */
    public function operators(): array;

    /**
     * This viewer's value or values on this axis, which the engine compares
     * against the campaign's rules.
     *
     * Return a list when the viewer can be several things at once — a member
     * of three groups, a discussion carrying four tags — and a scalar when
     * they can only be one. Null means "unknown for this viewer", and a
     * campaign that constrains this dimension will not match.
     *
     * Called once per request, on the serving path, so it must not query:
     * everything you need is already loaded on the actor, on the request, or
     * in the API document the page is about to send anyway.
     *
     * If your axis genuinely cannot be resolved on the server — viewport
     * width, the viewer's local hour — say so with {@see self::isServerSide()}
     * and resolve it in a client-side resolver registered under the same key.
     *
     * @return list<string>|string|int|null
     */
    public function resolve(TargetingContext $context): array|string|int|null;

    /**
     * Whether this axis can be decided before the page is sent.
     *
     * A server-side dimension is applied while the plan is built, so a
     * campaign that fails it never reaches the browser at all — which is what
     * keeps group targeting correct, since Flarum strips hidden groups out of
     * the payload and the client genuinely cannot see them.
     *
     * A dimension that returns false has its rules passed through to the
     * client to evaluate, which means they are visible in the page source.
     * Do not decide anything that has to stay private this way.
     */
    public function isServerSide(): bool;

    /**
     * The values an administrator can pick from, in the order they should be
     * offered.
     *
     * A list of pairs rather than a value-keyed map, because PHP silently
     * turns a numeric string key back into an integer: `$options['3']` is
     * `$options[3]`, so a map would hand the admin client group ids typed
     * differently from tag slugs for no reason the reader could see.
     *
     * `label` is a translation key where one exists and a literal otherwise —
     * a tag is called whatever the forum called it.
     *
     * Empty means the rule editor should offer free text. Called only in the
     * admin panel, so it may query.
     *
     * @return list<array{value: string, label: string}>
     */
    public function options(): array;
}
