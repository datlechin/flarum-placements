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
 * The language the forum is being read in.
 *
 * Worth knowing what this is not: Flarum does not negotiate `Accept-Language`.
 * It reads a signed-in member's saved preference, then a `locale` cookie, then
 * falls back to the forum default. So a German visitor arriving for the first
 * time resolves to the forum's default language, not to German, and only
 * becomes German once they have chosen. Targeting a locale therefore reaches
 * people who have *set* that language rather than everyone who speaks it.
 */
class LocaleDimension extends AbstractDimension
{
    public function key(): string
    {
        return 'locale';
    }

    public function resolve(TargetingContext $context): ?string
    {
        $locale = $context->request->getAttribute('locale');

        return is_string($locale) && $locale !== '' ? $locale : null;
    }
}
