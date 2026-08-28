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
use DateTimeZone;

/**
 * Which hours of the week a campaign may run in.
 *
 * 168 bits — seven days of twenty-four hours — stored as 42 hexadecimal
 * characters. Bit `n` is hour `n % 24` of day `intdiv($n, 24)`, counting days
 * from Monday.
 *
 * The hours are the *forum's*, not the reader's. Flarum locks the server to
 * UTC and stores no timezone for anybody, so viewer-local hours could only be
 * decided in the browser — which would mean shipping the mask to every visitor
 * and being unable to enforce it. "Nine to five" is what an administrator
 * means when they set this, and a forum has one nine-to-five.
 *
 * The timezone the hours are read in is a setting, because a forum in Hanoi
 * and a forum in Berlin do not agree about when the evening is.
 */
final class Daypart
{
    public const HOURS = 168;

    /**
     * 42 hex characters, or nothing at all.
     */
    public const LENGTH = 42;

    /**
     * Whether a campaign may run at this moment.
     *
     * A missing or malformed mask means "always". A campaign that quietly
     * stopped running because its schedule was stored wrong is far harder to
     * diagnose than one that ran when it should not have.
     */
    public static function allows(?string $mask, ?Carbon $now = null, string $timezone = 'UTC'): bool
    {
        if ($mask === null || ! self::isValid($mask)) {
            return true;
        }

        return self::isSet($mask, self::indexFor($now, $timezone));
    }

    public static function isValid(?string $mask): bool
    {
        return is_string($mask) && strlen($mask) === self::LENGTH && ctype_xdigit($mask);
    }

    /**
     * Which of the 168 slots a moment falls in.
     */
    public static function indexFor(?Carbon $now = null, string $timezone = 'UTC'): int
    {
        $local = ($now ?? Carbon::now())->copy()->setTimezone(self::zone($timezone));

        // `dayOfWeekIso` is 1 for Monday through 7 for Sunday, which is the
        // order the grid in the admin panel is drawn in.
        return ($local->dayOfWeekIso - 1) * 24 + $local->hour;
    }

    /**
     * Whether one hour is switched on.
     *
     * The mask is read most-significant-nibble first, so the string reads left
     * to right as Monday midnight onwards — which is what somebody debugging
     * one by eye will assume.
     */
    public static function isSet(string $mask, int $index): bool
    {
        if ($index < 0 || $index >= self::HOURS) {
            return false;
        }

        $nibble = hexdec($mask[intdiv($index, 4)]);
        $bit = 3 - ($index % 4);

        return ((int) $nibble & (1 << $bit)) !== 0;
    }

    /**
     * Build a mask from the hours that are on. For the admin panel and for
     * tests.
     *
     * @param  iterable<int>  $hours
     */
    public static function fromHours(iterable $hours): string
    {
        $bits = array_fill(0, self::HOURS, 0);

        foreach ($hours as $hour) {
            if ($hour >= 0 && $hour < self::HOURS) {
                $bits[$hour] = 1;
            }
        }

        $mask = '';

        for ($nibble = 0; $nibble < self::LENGTH; $nibble++) {
            $value = 0;

            for ($bit = 0; $bit < 4; $bit++) {
                $value |= $bits[$nibble * 4 + $bit] << (3 - $bit);
            }

            $mask .= dechex($value);
        }

        return $mask;
    }

    /**
     * A mask with every hour on, which is the same as having no mask.
     */
    public static function always(): string
    {
        return str_repeat('f', self::LENGTH);
    }

    private static function zone(string $timezone): DateTimeZone
    {
        try {
            return new DateTimeZone($timezone);
        } catch (\Exception) {
            // A timezone the server does not recognise falls back to UTC
            // rather than throwing on the serving path of every page view.
            return new DateTimeZone('UTC');
        }
    }
}
