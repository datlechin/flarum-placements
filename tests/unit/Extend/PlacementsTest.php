<?php

/*
 * This file is part of datlechin/flarum-placements.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\Placements\Tests\unit\Extend;

use Datlechin\Placements\Creative\CreativeTypeInterface;
use Datlechin\Placements\Extend\Placements;
use Datlechin\Placements\Placement;
use Datlechin\Placements\PlacementRegistry;
use Datlechin\Placements\PlacementServiceProvider;
use Datlechin\Placements\Targeting\DimensionInterface;
use Datlechin\Placements\Targeting\TargetingContext;
use Illuminate\Container\Container;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PlacementsTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container();

        // The same three bindings PlacementServiceProvider makes. The provider
        // itself needs a full Flarum application to construct, so booting it is
        // the integration suite's job; what matters here is that the extender
        // composes correctly with whatever is already bound.
        $this->container->singleton(PlacementServiceProvider::PLACEMENTS, fn () => [new Placement(key: 'notice')]);
        $this->container->singleton(PlacementServiceProvider::CREATIVE_TYPES, fn () => []);
        $this->container->singleton(PlacementServiceProvider::DIMENSIONS, fn () => []);
    }

    #[Test]
    public function it_appends_placements_to_what_is_already_registered(): void
    {
        (new Placements())
            ->placement(new Placement(key: 'acme.rail', label: 'acme.rail'))
            ->extend($this->container);

        $registry = new PlacementRegistry($this->container->make(PlacementServiceProvider::PLACEMENTS));

        $this->assertSame(['notice', 'acme.rail'], $registry->keys());
    }

    #[Test]
    public function it_takes_several_placements_at_once(): void
    {
        (new Placements())
            ->placement(
                new Placement(key: 'acme.one', label: 'acme.one'),
                new Placement(key: 'acme.two', label: 'acme.two'),
            )
            ->extend($this->container);

        $this->assertCount(3, $this->container->make(PlacementServiceProvider::PLACEMENTS));
    }

    #[Test]
    public function several_extensions_all_get_their_placements_in(): void
    {
        (new Placements())->placement(new Placement(key: 'acme.rail', label: 'x'))->extend($this->container);
        (new Placements())->placement(new Placement(key: 'other.rail', label: 'x'))->extend($this->container);

        $this->assertSame(
            ['notice', 'acme.rail', 'other.rail'],
            array_map(fn (Placement $p) => $p->key, $this->container->make(PlacementServiceProvider::PLACEMENTS))
        );
    }

    #[Test]
    public function an_extender_registered_before_the_binding_exists_still_applies(): void
    {
        // This is the ordering that actually happens: extenders run in whatever
        // order extensions load, so a third party's Placements extender can run
        // before this extension's service provider has bound anything. Laravel
        // records the extend() callback against an unbound abstract and applies
        // it on first resolve, which is what makes the ordering irrelevant.
        $container = new Container();

        (new Placements())
            ->placement(new Placement(key: 'acme.rail', label: 'x'))
            ->extend($container);

        $container->singleton(PlacementServiceProvider::PLACEMENTS, fn () => [new Placement(key: 'notice')]);

        $this->assertSame(
            ['notice', 'acme.rail'],
            array_map(fn (Placement $p) => $p->key, $container->make(PlacementServiceProvider::PLACEMENTS))
        );
    }

    #[Test]
    public function creative_types_and_dimensions_stay_class_strings_until_something_needs_them(): void
    {
        // They are resolved from the container only at the point of use, so
        // registering one for a feature the administrator has switched off
        // costs nothing at boot.
        (new Placements())
            ->creativeType(FakeCreativeType::class)
            ->dimension(FakeDimension::class)
            ->extend($this->container);

        $this->assertSame([FakeCreativeType::class], $this->container->make(PlacementServiceProvider::CREATIVE_TYPES));
        $this->assertSame([FakeDimension::class], $this->container->make(PlacementServiceProvider::DIMENSIONS));
    }

    #[Test]
    public function an_extender_that_registers_nothing_leaves_the_container_alone(): void
    {
        (new Placements())->extend($this->container);

        $this->assertCount(1, $this->container->make(PlacementServiceProvider::PLACEMENTS));
        $this->assertSame([], $this->container->make(PlacementServiceProvider::CREATIVE_TYPES));
        $this->assertSame([], $this->container->make(PlacementServiceProvider::DIMENSIONS));
    }

    #[Test]
    public function the_extender_is_fluent_all_the_way_through(): void
    {
        $extender = new Placements();

        $this->assertSame($extender, $extender->placement(new Placement(key: 'a')));
        $this->assertSame($extender, $extender->creativeType(FakeCreativeType::class));
        $this->assertSame($extender, $extender->dimension(FakeDimension::class));
    }
}

class FakeCreativeType implements CreativeTypeInterface
{
    public function key(): string
    {
        return 'fake';
    }

    public function label(): string
    {
        return 'fake.label';
    }

    public function rules(): array
    {
        return [];
    }

    public function normalize(array $payload): array
    {
        return $payload;
    }

    public function assets(array $payload): array
    {
        return [];
    }

    public function requiredPermission(): ?string
    {
        return null;
    }
}

class FakeDimension implements DimensionInterface
{
    public function key(): string
    {
        return 'fake';
    }

    public function label(): string
    {
        return 'fake.label';
    }

    public function operators(): array
    {
        return [self::IS];
    }

    public function resolve(TargetingContext $context): array|string|int|null
    {
        return null;
    }

    public function isServerSide(): bool
    {
        return true;
    }

    public function options(): array
    {
        return [];
    }
}
