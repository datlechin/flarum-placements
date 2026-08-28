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
use Flarum\Group\Group;

/**
 * The groups the viewer belongs to.
 *
 * Server-side and not negotiable. Flarum strips hidden groups out of the user
 * it serialises into the payload, so a client-side reading of this axis would
 * silently stop matching for exactly the groups a forum tends to use for
 * supporters and staff — the ones most likely to be targeted or excluded.
 *
 * Includes the synthetic guest and member groups, so "exclude members" works
 * without anybody having to create a real group for it.
 */
class GroupDimension extends AbstractDimension
{
    public function key(): string
    {
        return 'group';
    }

    /**
     * @return list<string>
     */
    public function resolve(TargetingContext $context): array
    {
        return array_values(array_map(strval(...), $context->actor->permissionGroupIds()));
    }

    public function options(): array
    {
        $options = [];

        /** @var Group $group */
        foreach (Group::query()->orderBy('name_singular')->get() as $group) {
            $options[] = ['value' => (string) $group->id, 'label' => (string) $group->name_plural];
        }

        return $options;
    }
}
