<?php

/*
 * This file is part of datlechin/flarum-placement.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\Placement\Support;

use DateTimeZone;
use Flarum\Settings\SettingsRepositoryInterface;

/**
 * The handful of things that really are settings.
 *
 * Campaigns and creatives are records and live in their own tables, for a
 * reason worth repeating: writing through the settings API dispatches
 * `Settings\Event\Saved`, which restarts every queue worker on the forum.
 * What is left here is a feature flag and a timezone.
 */
final class Settings
{
    public const PREFIX = 'datlechin-placement.';

    /**
     * The timezone dayparting hours are read in.
     *
     * Flarum locks the server to UTC and stores no timezone for anybody, so
     * without this "nine to five" would mean nine to five UTC — which on a
     * forum in Hanoi is the middle of the night.
     */
    public const TIMEZONE = self::PREFIX.'timezone';

    /**
     * How many days of hourly statistics to keep.
     */
    public const RETENTION_DAYS = self::PREFIX.'retention_days';

    /**
     * The contents of `/ads.txt`.
     *
     * A setting rather than a file on disk, so a forum owner can authorise a
     * seller without shell access. Empty means the route answers 404, which is
     * what the specification asks for — an empty file would mean "nobody is
     * authorised" and stop every network buying the inventory.
     */
    public const ADS_TXT = self::PREFIX.'ads_txt';

    /**
     * Loader scripts to put in the head, one URL per line.
     *
     * A setting rather than part of a creative because a network's loader is
     * nominated once for the whole forum, not once per advert — and because
     * loading it twice is how a network's own script starts arguing with
     * itself.
     */
    public const NETWORK_SCRIPTS = self::PREFIX.'network_scripts';

    /**
     * The settings a configuration bundle carries.
     *
     * Named rather than taken from a prefix scan, so that a setting added
     * later has to be considered before it travels between forums.
     *
     * @var list<string>
     */
    public const EXPORTABLE = [self::TIMEZONE, self::RETENTION_DAYS, self::ADS_TXT, self::NETWORK_SCRIPTS];

    public const DEFAULT_TIMEZONE = 'UTC';

    public const DEFAULT_RETENTION_DAYS = 90;

    public const MIN_RETENTION_DAYS = 1;

    public const MAX_RETENTION_DAYS = 730;

    /**
     * @return array<string, string|int>
     */
    public static function defaults(): array
    {
        return [
            self::TIMEZONE => self::DEFAULT_TIMEZONE,
            self::RETENTION_DAYS => self::DEFAULT_RETENTION_DAYS,
            self::ADS_TXT => '',
            self::NETWORK_SCRIPTS => '',
        ];
    }

    /**
     * A timezone the server actually knows.
     *
     * A stored value the server does not recognise falls back rather than
     * throwing: this is read on the serving path of every page view, and a
     * schedule read in the wrong hours is a far smaller failure than a forum
     * that will not render.
     */
    public static function timezone(SettingsRepositoryInterface $settings): string
    {
        $stored = $settings->get(self::TIMEZONE);

        if (! is_string($stored) || $stored === '') {
            return self::DEFAULT_TIMEZONE;
        }

        try {
            new DateTimeZone($stored);
        } catch (\Exception) {
            return self::DEFAULT_TIMEZONE;
        }

        return $stored;
    }

    public static function retentionDays(SettingsRepositoryInterface $settings): int
    {
        $stored = $settings->get(self::RETENTION_DAYS);

        if (! is_numeric($stored)) {
            return self::DEFAULT_RETENTION_DAYS;
        }

        return max(self::MIN_RETENTION_DAYS, min(self::MAX_RETENTION_DAYS, (int) $stored));
    }
}
