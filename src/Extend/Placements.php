<?php

/*
 * This file is part of datlechin/flarum-placement.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\Placement\Extend;

use Datlechin\Placement\Creative\CreativeTypeInterface;
use Datlechin\Placement\Placement;
use Datlechin\Placement\PlacementServiceProvider;
use Datlechin\Placement\Targeting\DimensionInterface;
use Flarum\Extend\ExtenderInterface;
use Flarum\Extension\Extension;
use Illuminate\Contracts\Container\Container;

/**
 * Adds placements, creative types and targeting dimensions from another
 * extension.
 *
 * ```php
 * // extend.php
 * return [
 *     (new Datlechin\Placement\Extend\Placements())
 *         ->placement(new Datlechin\Placement\Placement(
 *             key: 'acme.profile_rail',
 *             group: 'user',
 *             label: 'acme-widgets.admin.placements.profile_rail.label',
 *             recommendedSize: [160, 600],
 *             reserveDesktop: 600,
 *         ))
 *         ->creativeType(Acme\Creative\VideoType::class)
 *         ->dimension(Acme\Targeting\CountryDimension::class),
 * ];
 * ```
 *
 * Registering a placement only tells the admin panel and the serving engine
 * that the slot exists. Something still has to render it: add an `<AdSlot>`
 * under the same key to whichever `ItemList` you want it in, and namespace the
 * key with your own prefix so it cannot collide with anybody else's.
 *
 * Creative types and dimensions are given as class strings and resolved from
 * the container only when they are used, so registering one for a feature the
 * administrator has switched off costs nothing.
 */
class Placements implements ExtenderInterface
{
    /**
     * @var list<Placement>
     */
    private array $placements = [];

    /**
     * @var list<class-string<CreativeTypeInterface>>
     */
    private array $creativeTypes = [];

    /**
     * @var list<class-string<DimensionInterface>>
     */
    private array $dimensions = [];

    /**
     * Declare one or more slots your own frontend code renders.
     */
    public function placement(Placement ...$placements): self
    {
        array_push($this->placements, ...$placements);

        return $this;
    }

    /**
     * Add a kind of creative, with its own validation, payload shape and
     * frontend renderer.
     *
     * @param  class-string<CreativeTypeInterface>  $type
     */
    public function creativeType(string $type): self
    {
        $this->creativeTypes[] = $type;

        return $this;
    }

    /**
     * Add an axis campaigns can be targeted along.
     *
     * @param  class-string<DimensionInterface>  $dimension
     */
    public function dimension(string $dimension): self
    {
        $this->dimensions[] = $dimension;

        return $this;
    }

    public function extend(Container $container, ?Extension $extension = null): void
    {
        $this->append($container, PlacementServiceProvider::PLACEMENTS, $this->placements);
        $this->append($container, PlacementServiceProvider::CREATIVE_TYPES, $this->creativeTypes);
        $this->append($container, PlacementServiceProvider::DIMENSIONS, $this->dimensions);
    }

    /**
     * @param  list<mixed>  $additions
     */
    private function append(Container $container, string $abstract, array $additions): void
    {
        if (! $additions) {
            return;
        }

        $container->extend(
            $abstract,
            /**
             * @param  list<mixed>  $existing
             * @return list<mixed>
             */
            fn (array $existing) => [...$existing, ...$additions]
        );
    }
}
