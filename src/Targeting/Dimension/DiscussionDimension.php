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
 * One specific discussion.
 *
 * For the sponsorship that is attached to a thread rather than to a section —
 * an announcement, a release thread, a contest. Free to resolve: the id is in
 * the route.
 */
class DiscussionDimension extends AbstractDimension
{
    public function key(): string
    {
        return 'discussion';
    }

    public function resolve(TargetingContext $context): ?string
    {
        if ($context->routeName() !== 'discussion') {
            return null;
        }

        $id = $context->routeParameter('id');

        // Flarum's discussion route matches `{id}` as `12-some-slug`, so the
        // number has to be split off or every rule would have to be written
        // against a slug that changes when the title is edited.
        return $id === null ? null : (strstr($id, '-', true) ?: $id);
    }
}
