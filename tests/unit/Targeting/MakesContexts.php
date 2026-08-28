<?php

/*
 * This file is part of datlechin/flarum-placement.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\Placement\Tests\unit\Targeting;

use Datlechin\Placement\Targeting\TargetingContext;
use Flarum\Group\Group;
use Flarum\User\Guest;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Collection;
use Laminas\Diactoros\ServerRequest;

/**
 * Builds the context a dimension is handed, without a database.
 *
 * Group membership is set as a loaded relation rather than saved, which is
 * also how it arrives in production: the actor's groups are already on the
 * model by the time the frontend payload is assembled.
 */
trait MakesContexts
{
    /**
     * @param  array<string, mixed>  $attributes  Request attributes, e.g. routeName, routeParameters, locale.
     * @param  array<string, mixed>|null  $apiDocument
     */
    protected function context(
        ?User $actor = null,
        array $attributes = [],
        ?array $apiDocument = null,
    ): TargetingContext {
        $request = new ServerRequest();

        foreach ($attributes as $name => $value) {
            $request = $request->withAttribute($name, $value);
        }

        return new TargetingContext($request, $actor ?? new Guest(), $apiDocument);
    }

    /**
     * @param  list<int>  $groupIds  Real groups, on top of the synthetic guest and member ones.
     */
    protected function member(array $groupIds = [], array $attributes = []): User
    {
        $user = new User();
        $user->forceFill(array_merge(['id' => 1, 'is_email_confirmed' => true], $attributes));

        $user->setRelation('groups', new Collection(array_map(
            function (int $id) {
                $group = new Group();
                $group->forceFill(['id' => $id]);

                return $group;
            },
            $groupIds
        )));

        return $user;
    }
}
