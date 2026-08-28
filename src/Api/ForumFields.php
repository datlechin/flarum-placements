<?php

/*
 * This file is part of datlechin/flarum-placement.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\Placement\Api;

use Datlechin\Placement\Creative\CreativeTypeRegistry;
use Datlechin\Placement\Creative\Type\RawHtmlType;
use Datlechin\Placement\PlacementRegistry;
use Datlechin\Placement\PlacementServiceProvider;
use Datlechin\Placement\Support\Permissions;
use Datlechin\Placement\Targeting\DimensionInterface;
use Flarum\Api\Context;
use Flarum\Api\Schema;
use Illuminate\Contracts\Container\Container;

/**
 * What the admin client needs to render its forms.
 *
 * Placements and targeting dimensions are declared in code, so they cannot be
 * listed over a CRUD endpoint — there is nothing to list. They travel on the
 * forum resource instead, gated on the manage permission.
 *
 * The gate is not decorative. `Extend\Settings::serializeToForum` has no
 * visibility callback at all, so anything registered that way is readable by
 * every guest in view-source; these go through `Extend\ApiResource` precisely
 * so that they can be hidden.
 */
class ForumFields
{
    public function __construct(protected Container $container)
    {
    }

    /**
     * @return list<Schema\Attribute>
     */
    public function __invoke(): array
    {
        $canManage = fn (mixed $model, Context $context) => $context->getActor()->hasPermission(Permissions::MANAGE);

        return [
            Schema\Boolean::make('canManagePlacements')
                ->get(fn (mixed $model, Context $context) => $context->getActor()->hasPermission(Permissions::MANAGE)),

            Schema\Arr::make('placementSlots')
                ->visible($canManage)
                ->get(fn () => $this->container->make(PlacementRegistry::class)->toArray()),

            Schema\Arr::make('placementDimensions')
                ->visible($canManage)
                ->get(fn () => $this->dimensions()),

            // Only the types this actor may actually author, so the form never
            // offers a choice that will be refused on save.
            Schema\Arr::make('placementCreativeTypes')
                ->visible($canManage)
                ->get(fn (mixed $model, Context $context) => $this->creativeTypes($context)),
        ];
    }

    /**
     * The creative types this actor may author.
     *
     * Raw HTML needs both a permission and a flag in `config.php`, so it is
     * absent from the list until both are in place rather than present and
     * refused on save.
     *
     * @return list<array<string, mixed>>
     */
    protected function creativeTypes(Context $context): array
    {
        $actor = $context->getActor();
        $registry = $this->container->make(CreativeTypeRegistry::class);

        $available = $registry->availableTo(fn (string $permission) => $actor->hasPermission($permission));

        $types = [];

        foreach ($available as $key => $type) {
            if ($type instanceof RawHtmlType && ! $type->isEnabled()) {
                continue;
            }

            $types[] = ['key' => $key, 'label' => $type->label()];
        }

        return $types;
    }

    /**
     * Every registered dimension, with the values an administrator may pick.
     *
     * `options()` may query — it lists the forum's groups and tags — which is
     * why this is on an admin-only field rather than in the serving payload.
     *
     * @return list<array<string, mixed>>
     */
    protected function dimensions(): array
    {
        /** @var list<class-string<DimensionInterface>> $classes */
        $classes = $this->container->make(PlacementServiceProvider::DIMENSIONS);

        $dimensions = [];

        foreach ($classes as $class) {
            /** @var DimensionInterface $dimension */
            $dimension = $this->container->make($class);

            $dimensions[] = [
                'key' => $dimension->key(),
                'label' => $dimension->label(),
                'operators' => $dimension->operators(),
                'serverSide' => $dimension->isServerSide(),
                'options' => $dimension->options(),
            ];
        }

        return $dimensions;
    }
}
