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

use Datlechin\Placement\Targeting\DimensionInterface;
use Datlechin\Placement\Targeting\TargetingContext;

/**
 * How many posts the viewer has written.
 *
 * A quantity rather than a set, so it takes "at least" and "at most" instead
 * of "is". The usual shape is a house campaign aimed at people who have not
 * posted yet, or leaving long-standing members alone.
 *
 * Guests resolve to null rather than to zero: a guest has not written zero
 * posts, we simply do not know who they are, and "at most 0 posts" should not
 * quietly become a campaign that runs for every visitor on the forum.
 */
class PostCountDimension extends AbstractDimension
{
    public function key(): string
    {
        return 'posts';
    }

    public function operators(): array
    {
        return [DimensionInterface::GTE, DimensionInterface::LTE];
    }

    public function resolve(TargetingContext $context): ?int
    {
        return $context->isGuest() ? null : (int) $context->actor->comment_count;
    }
}
