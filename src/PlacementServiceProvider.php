<?php

/*
 * This file is part of datlechin/flarum-placements.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\Placements;

use Datlechin\Placements\Creative\CreativeTypeInterface;
use Datlechin\Placements\Creative\CreativeTypeRegistry;
use Datlechin\Placements\Creative\Type;
use Datlechin\Placements\Model\Assignment;
use Datlechin\Placements\Model\Campaign;
use Datlechin\Placements\Model\CampaignRule;
use Datlechin\Placements\Model\Creative;
use Datlechin\Placements\Measurement\EventToken;
use Datlechin\Placements\Measurement\Recorder;
use Datlechin\Placements\Measurement\SigningKey;
use Datlechin\Placements\Model\PlacementSetting;
use Datlechin\Placements\Selection\PlanBuilder;
use Datlechin\Placements\Selection\PlanSource;
use Datlechin\Placements\Support\Settings;
use Datlechin\Placements\Targeting\Dimension;
use Datlechin\Placements\Targeting\DimensionInterface;
use Flarum\Foundation\AbstractServiceProvider;
use Flarum\Notification\NotificationSyncer;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\ConnectionInterface;

/**
 * Wires the extension up.
 *
 * Everything bound here is lazy, so registering the provider costs nothing on
 * a request that never serves anything. The three raw lists exist so that
 * `Extend\Placements` has something to append to: Laravel records an
 * `extend()` callback against an abstract that has not been bound yet and
 * applies it on first resolve, so a third-party extender may run before or
 * after this provider and the result is the same either way.
 */
class PlacementServiceProvider extends AbstractServiceProvider
{
    /**
     * Raw `Placement` value objects, before the registry validates them.
     */
    public const PLACEMENTS = 'datlechin-placements.placements';

    /**
     * @see CreativeTypeInterface
     */
    public const CREATIVE_TYPES = 'datlechin-placements.creative-types';

    /**
     * @see DimensionInterface
     */
    public const DIMENSIONS = 'datlechin-placements.dimensions';

    public function register(): void
    {
        // Each of these resolves to a plain list that `Extend\Placements`
        // appends to. The element types are named on the constants above.
        $this->container->singleton(self::PLACEMENTS, fn () => BuiltInPlacements::all());
        $this->container->singleton(self::CREATIVE_TYPES, fn () => [
            Type\ImageType::class,
            Type\TextType::class,
            Type\RichTextType::class,
            Type\LogoWallType::class,
            Type\RawHtmlType::class,
            Type\NetworkType::class,
        ]);
        $this->container->singleton(self::DIMENSIONS, fn () => [
            Dimension\VisitorDimension::class,
            Dimension\GroupDimension::class,
            Dimension\RouteDimension::class,
            Dimension\DiscussionDimension::class,
            Dimension\LocaleDimension::class,
            Dimension\PostCountDimension::class,
            Dimension\AccountAgeDimension::class,
        ]);

        $this->container->singleton(
            PlacementRegistry::class,
            fn (Container $container) => new PlacementRegistry($container->make(self::PLACEMENTS))
        );

        $this->container->singleton(
            CreativeTypeRegistry::class,
            fn (Container $container) => new CreativeTypeRegistry($container, $container->make(self::CREATIVE_TYPES))
        );

        $this->container->singleton(
            PlanSource::class,
            fn (Container $container) => new PlanSource($container->make(Cache::class))
        );

        $this->container->singleton(
            SigningKey::class,
            fn (Container $container) => new SigningKey($container->make('flarum.settings'))
        );

        // Resolved lazily so the key is generated on first use rather than on
        // every boot.
        $this->container->singleton(
            EventToken::class,
            fn (Container $container) => new EventToken($container->make(SigningKey::class)->get())
        );

        $this->container->singleton(
            Recorder::class,
            fn (Container $container) => new Recorder(
                $container->make(Cache::class),
                $container->make(ConnectionInterface::class),
                $container->make(NotificationSyncer::class),
            )
        );

        $this->container->singleton(
            PlanBuilder::class,
            fn (Container $container) => new PlanBuilder(
                $container->make(PlanSource::class),
                $container->make(PlacementRegistry::class),
                $container,
                $container->make(EventToken::class),
                Settings::timezone($container->make('flarum.settings')),
            )
        );

        $this->container->singleton(
            PlacementPlan::class,
            fn (Container $container) => new PlacementPlan(
                $container->make(PlacementRegistry::class),
                $container->make(Cache::class),
                $container->make(PlanBuilder::class),
            )
        );
    }

    public function boot(): void
    {
        // Both caches are held forever, so they have to be dropped when
        // anything they describe changes. Hooking the models rather than the
        // controllers means an import, a console command and a direct save all
        // invalidate them too.
        $flushSlots = function (): void {
            $this->container->make(PlacementPlan::class)->flush();
        };

        PlacementSetting::saved($flushSlots);
        PlacementSetting::deleted($flushSlots);

        $flushPlan = function (): void {
            $this->container->make(PlanSource::class)->flush();
        };

        foreach ([Campaign::class, Creative::class, CampaignRule::class, Assignment::class] as $model) {
            $model::saved($flushPlan);
            $model::deleted($flushPlan);
        }
    }
}
