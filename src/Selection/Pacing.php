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

/**
 * Stops a campaign spending a month of inventory in three days.
 *
 * Deliberately *not* inventory forecasting. Real even delivery means
 * predicting how many matching impressions the forum will have between now and
 * the end date, per targeting combination, and reforecasting whenever anything
 * changes — a subsystem of months, and the single largest trap in this whole
 * design. It is seductive precisely because "guaranteed" is one of the tier
 * names.
 *
 * What this does instead is compare how much of the flight has elapsed with
 * how much of the cap has been spent, and thin the campaign out when it is
 * ahead. It will not hit the goal exactly. It stops the three-day burnout,
 * which is the complaint people actually have.
 */
final class Pacing
{
    /**
     * A campaign is never thinned out completely.
     *
     * A campaign throttled to zero cannot recover: if the forum goes quiet it
     * will never catch back up, and it would sit at nothing while its flight
     * ran out. Leaving a small chance means the throttle self-corrects.
     */
    public const FLOOR = 0.05;

    /**
     * How likely a paced campaign is to be served this time, from 0 to 1.
     *
     * @param  float|null  $elapsed  How far through the flight, or null when it has no bounded flight.
     * @param  float|null  $delivered  How much of the cap is spent, or null when there is no cap.
     */
    public static function keepProbability(?float $elapsed, ?float $delivered): float
    {
        // Even delivery needs something to be even against. Without both a
        // flight and a cap there is no schedule to be ahead of, and pacing is
        // simply not applicable — not "always throttle", which is what
        // treating either as zero would produce.
        if ($elapsed === null || $delivered === null) {
            return 1.0;
        }

        // On schedule or behind it: serve freely. This is the common case, and
        // it costs one comparison.
        if ($delivered <= $elapsed) {
            return 1.0;
        }

        if ($delivered <= 0.0) {
            return 1.0;
        }

        return max(self::FLOOR, $elapsed / $delivered);
    }

    /**
     * Whether a campaign should be served on this particular page view.
     *
     * `$roll` is injectable so the decision can be tested; production passes
     * nothing and gets `random_int`.
     *
     * @param  array<string, mixed>  $campaign  A row from the cached plan.
     * @param  (callable(): float)|null  $roll  Returns a number in [0, 1).
     */
    public static function shouldServe(array $campaign, ?Carbon $now = null, ?callable $roll = null): bool
    {
        if (($campaign['pacing'] ?? Campaign::PACING_ASAP) !== Campaign::PACING_EVEN) {
            return true;
        }

        // A house campaign fills space nobody paid for. Pacing it would leave
        // holes on the page for no benefit to anyone.
        if ($campaign['is_house'] ?? false) {
            return true;
        }

        $keep = self::keepProbability(
            self::elapsedFraction($campaign, $now),
            self::deliveredFraction($campaign)
        );

        if ($keep >= 1.0) {
            return true;
        }

        $roll ??= static fn (): float => random_int(0, PHP_INT_MAX - 1) / PHP_INT_MAX;

        return $roll() < $keep;
    }

    /**
     * @param  array<string, mixed>  $campaign
     */
    public static function elapsedFraction(array $campaign, ?Carbon $now = null): ?float
    {
        $startsAt = self::time($campaign['starts_at'] ?? null);
        $endsAt = self::time($campaign['ends_at'] ?? null);

        if ($startsAt === null || $endsAt === null) {
            return null;
        }

        $total = $endsAt->getTimestamp() - $startsAt->getTimestamp();

        if ($total <= 0) {
            return null;
        }

        $elapsed = ($now ?? Carbon::now())->getTimestamp() - $startsAt->getTimestamp();

        return max(0.0, min(1.0, $elapsed / $total));
    }

    /**
     * How much of the cap has been spent.
     *
     * Impressions are preferred when both caps exist, because that is what a
     * flight is normally sold on and it is the larger number, so it paces more
     * smoothly.
     *
     * @param  array<string, mixed>  $campaign
     */
    public static function deliveredFraction(array $campaign): ?float
    {
        foreach ([['max_impressions', 'impressions'], ['max_clicks', 'clicks']] as [$capKey, $countKey]) {
            $cap = self::number($campaign[$capKey] ?? null);

            if ($cap > 0) {
                return min(1.0, self::number($campaign[$countKey] ?? null) / $cap);
            }
        }

        return null;
    }

    private static function number(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private static function time(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }

        return is_string($value) && $value !== '' ? Carbon::parse($value) : null;
    }
}
