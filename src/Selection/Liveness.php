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
 * Whether a campaign may run right now.
 *
 * @phpstan-type LivenessAttributes array{
 *     status: string,
 *     starts_at: string|Carbon|null,
 *     ends_at: string|Carbon|null,
 *     is_house: bool,
 *     max_impressions: int|null,
 *     max_clicks: int|null,
 *     impressions: int,
 *     clicks: int
 * }
 *
 * Pure, and deliberately the only implementation: the model asks it, and so
 * does the serving path, which works from a cached array rather than from
 * models. Two copies of this rule would drift, and the way you would find out
 * is an advertiser noticing their campaign ran a day longer than they paid
 * for.
 */
final class Liveness
{
    /**
     * @param  LivenessAttributes  $campaign
     */
    public static function of(array $campaign, ?Carbon $now = null): bool
    {
        $now ??= Carbon::now();
        $status = $campaign['status'];

        // An administrator who paused a campaign means it, whatever the dates
        // say.
        if (in_array($status, [Campaign::STATUS_DRAFT, Campaign::STATUS_PAUSED, Campaign::STATUS_ARCHIVED], true)) {
            return false;
        }

        $startsAt = self::time($campaign['starts_at']);
        $endsAt = self::time($campaign['ends_at']);

        if ($startsAt !== null && $now->lt($startsAt)) {
            return false;
        }

        // Exclusive: a campaign booked to the 1st has not been booked to
        // include the 1st.
        if ($endsAt !== null && $now->gte($endsAt)) {
            return false;
        }

        return ! self::capped($campaign);
    }

    /**
     * @param  LivenessAttributes  $campaign
     */
    public static function capped(array $campaign): bool
    {
        // A house campaign exists to fill space nobody paid for, so capping it
        // would just leave holes on the page.
        if ($campaign['is_house']) {
            return false;
        }

        $maxImpressions = $campaign['max_impressions'];

        if ($maxImpressions !== null && $campaign['impressions'] >= $maxImpressions) {
            return true;
        }

        $maxClicks = $campaign['max_clicks'];

        return $maxClicks !== null && $campaign['clicks'] >= $maxClicks;
    }

    private static function time(string|Carbon|null $value): ?Carbon
    {
        if ($value === null || $value instanceof Carbon) {
            return $value;
        }

        return $value === '' ? null : Carbon::parse($value);
    }
}
