<?php

/*
 * This file is part of datlechin/flarum-placement.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\Placement\Targeting;

/**
 * Decides whether a set of targeting rules matches a viewer.
 *
 * Pure: it is given rules and the viewer's already-resolved values and does
 * nothing else. Everything expensive — reading groups, working out which tags
 * a page carries — happens once per request in the dimensions, not once per
 * campaign here.
 *
 * The rules are:
 *
 * - AND across dimensions. A campaign targeted at a tag *and* a group needs
 *   both.
 * - OR within a dimension. Three tag rules mean any of those three.
 * - An exclusion beats an inclusion. Targeted at "support" but excluded from
 *   "support" means excluded.
 * - A dimension with no rules does not constrain the campaign at all.
 *
 * And one that is less obvious, but is the difference between sensible and
 * maddening behaviour: when the viewer's value on an axis is unknown, an
 * inclusion cannot be confirmed and fails, while an exclusion cannot be
 * confirmed either and therefore does *not* fire. A campaign excluded from the
 * "nsfw" tag should still run on a user profile, which carries no tags at all.
 */
final class RuleEvaluator
{
    /**
     * @param  list<array{dimension: string, operator: string, value: string}>  $rules
     * @param  array<string, list<string>|string|int|null>  $viewer  Resolved value per dimension.
     */
    public static function matches(array $rules, array $viewer): bool
    {
        return self::firstFailure($rules, $viewer) === null;
    }

    /**
     * The dimension that stopped this campaign matching, or null when it
     * matched.
     *
     * This is what the "why is my ad not showing?" overlay reports, and it is
     * the reason the evaluator returns a dimension rather than a boolean
     * internally. Answering that question is most of the support an ad server
     * ever generates.
     *
     * @param  list<array{dimension: string, operator: string, value: string}>  $rules
     * @param  array<string, list<string>|string|int|null>  $viewer
     */
    public static function firstFailure(array $rules, array $viewer): ?string
    {
        foreach (self::byDimension($rules) as $dimension => $operators) {
            $value = $viewer[$dimension] ?? null;

            if (! self::dimensionMatches($operators, $value)) {
                return $dimension;
            }
        }

        return null;
    }

    /**
     * @param  array<string, list<string>>  $operators
     * @param  list<string>|string|int|null  $value
     */
    private static function dimensionMatches(array $operators, array|string|int|null $value): bool
    {
        $viewer = self::normalise($value);

        // Exclusions first: they win, and they are the cheapest to check.
        // Unknown means unexcludable, so an empty viewer value passes.
        foreach ($operators[DimensionInterface::IS_NOT] ?? [] as $excluded) {
            if (in_array($excluded, $viewer, true)) {
                return false;
            }
        }

        $included = $operators[DimensionInterface::IS] ?? [];

        if ($included !== [] && array_intersect($included, $viewer) === []) {
            return false;
        }

        foreach ($operators[DimensionInterface::GTE] ?? [] as $minimum) {
            if (! self::compares($value, fn (int|float $actual) => $actual >= (float) $minimum)) {
                return false;
            }
        }

        foreach ($operators[DimensionInterface::LTE] ?? [] as $maximum) {
            if (! self::compares($value, fn (int|float $actual) => $actual <= (float) $maximum)) {
                return false;
            }
        }

        return true;
    }

    /**
     * A quantity comparison, which needs a single number rather than a set.
     *
     * An unknown or non-numeric value fails: "at least 50 posts" cannot be
     * true of a viewer whose post count we do not have.
     *
     * @param  list<string>|string|int|null  $value
     * @param  callable(int|float): bool  $test
     */
    private static function compares(array|string|int|null $value, callable $test): bool
    {
        if (is_array($value) || $value === null || ! is_numeric($value)) {
            return false;
        }

        return $test($value + 0);
    }

    /**
     * @param  list<string>|string|int|null  $value
     * @return list<string>
     */
    private static function normalise(array|string|int|null $value): array
    {
        if ($value === null) {
            return [];
        }

        return array_values(array_map(strval(...), is_array($value) ? $value : [$value]));
    }

    /**
     * @param  list<array{dimension: string, operator: string, value: string}>  $rules
     * @return array<string, array<string, list<string>>>
     */
    private static function byDimension(array $rules): array
    {
        $grouped = [];

        foreach ($rules as $rule) {
            $grouped[$rule['dimension']][$rule['operator']][] = $rule['value'];
        }

        return $grouped;
    }
}
