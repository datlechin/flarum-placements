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

use Datlechin\Placement\Targeting\TargetingContext;

/**
 * Which page this is, by route name.
 *
 * Mostly used the other way round, as an exclusion. Google's publisher
 * policies treat a dead-end page — a 404, a settings screen, an empty search
 * result — as a page with no publisher content, and serving ads there is a
 * policy violation rather than a matter of taste.
 */
class RouteDimension extends AbstractDimension
{
    /**
     * Routes that carry no content of the forum's own. Offered as a one-click
     * exclusion in the admin panel rather than enforced, because what counts
     * as a dead end depends on the forum.
     */
    public const DEAD_ENDS = ['settings', 'notifications', 'logoutPage', 'resetPassword', 'confirmEmail'];

    public function key(): string
    {
        return 'route';
    }

    public function resolve(TargetingContext $context): ?string
    {
        return $context->routeName();
    }

    public function options(): array
    {
        $routes = ['index', 'posts', 'discussion', 'user', 'tag', 'tags', ...self::DEAD_ENDS];

        return array_map(
            fn (string $route) => [
                'value' => $route,
                'label' => "datlechin-placement.admin.dimensions.route.names.$route",
            ],
            $routes
        );
    }
}
