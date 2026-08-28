<?php

/*
 * This file is part of datlechin/flarum-placement.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\Placement\Targeting\Dimension;

use Carbon\Carbon;
use Datlechin\Placement\Targeting\DimensionInterface;
use Datlechin\Placement\Targeting\TargetingContext;

/**
 * How many whole days ago the viewer joined.
 *
 * Days rather than a date, so a rule keeps meaning the same thing as time
 * passes: "joined in the last week" written once stays "the last week".
 *
 * Null for guests, and null for an account with no join date, for the same
 * reason post count is: unknown must not read as zero, or "joined at most a
 * day ago" would match every visitor on the forum.
 */
class AccountAgeDimension extends AbstractDimension
{
    public function key(): string
    {
        return 'account_age';
    }

    public function operators(): array
    {
        return [DimensionInterface::GTE, DimensionInterface::LTE];
    }

    public function resolve(TargetingContext $context): ?int
    {
        if ($context->isGuest()) {
            return null;
        }

        $joined = $context->actor->joined_at;

        if (! $joined instanceof Carbon) {
            return null;
        }

        // Carbon 3 returns a float here; whole days is what a rule is written
        // in, so truncate rather than round — somebody 6.9 days old has not
        // been a member for a week.
        return max(0, (int) $joined->diffInDays(Carbon::now(), absolute: true));
    }
}
