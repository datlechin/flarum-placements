<?php

/*
 * This file is part of datlechin/flarum-placements.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\Placements\Targeting\Dimension;

use Datlechin\Placements\Targeting\TargetingContext;

/**
 * Signed in, or not.
 *
 * The single most useful axis on a public forum, and the one most likely to be
 * misjudged: guests are typically 60 to 90 per cent of impressions, so
 * "members only" is a much smaller campaign than it sounds and "guests only" a
 * much larger one.
 */
class VisitorDimension extends AbstractDimension
{
    public const GUEST = 'guest';
    public const MEMBER = 'member';

    public function key(): string
    {
        return 'visitor';
    }

    public function resolve(TargetingContext $context): string
    {
        return $context->isGuest() ? self::GUEST : self::MEMBER;
    }

    public function options(): array
    {
        return [
            ['value' => self::GUEST, 'label' => 'datlechin-placements.admin.dimensions.visitor.guest'],
            ['value' => self::MEMBER, 'label' => 'datlechin-placements.admin.dimensions.visitor.member'],
        ];
    }
}
