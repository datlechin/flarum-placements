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

use Datlechin\Placement\Targeting\Dimension;
use Datlechin\Placement\Targeting\DimensionInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Adding a dimension and forgetting its locale entry ships a rule editor whose
 * dropdown reads "datlechin-placement.admin.dimensions.foo.label".
 */
class DimensionLocaleTest extends TestCase
{
    public static function dimensions(): array
    {
        return array_map(fn (DimensionInterface $d) => [$d->key(), $d], [
            new Dimension\VisitorDimension(),
            new Dimension\GroupDimension(),
            new Dimension\RouteDimension(),
            new Dimension\DiscussionDimension(),
            new Dimension\LocaleDimension(),
            new Dimension\PostCountDimension(),
            new Dimension\AccountAgeDimension(),
            new Dimension\TagDimension(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function locale(): array
    {
        /** @var array<string, mixed> $parsed */
        $parsed = Yaml::parseFile(__DIR__.'/../../../locale/en.yml');

        return $parsed['datlechin-placement']['admin'] ?? [];
    }

    /**
     * @param  list<string>  $path
     */
    private static function at(array $path): mixed
    {
        $node = self::locale();

        foreach ($path as $segment) {
            if (! is_array($node) || ! array_key_exists($segment, $node)) {
                return null;
            }

            $node = $node[$segment];
        }

        return $node;
    }

    #[Test]
    #[DataProvider('dimensions')]
    public function every_dimension_has_a_name_in_the_rule_editor(string $key, DimensionInterface $dimension): void
    {
        $this->assertNotEmpty(self::at(['dimensions', $key, 'label']), "Dimension [$key] has no label.");
    }

    #[Test]
    #[DataProvider('dimensions')]
    public function every_operator_a_dimension_offers_has_a_word_for_it(string $key, DimensionInterface $dimension): void
    {
        foreach ($dimension->operators() as $operator) {
            $this->assertNotEmpty(
                self::at(['rules', 'operators', $operator]),
                "Operator [$operator], offered by dimension [$key], has no label."
            );
        }
    }

    #[Test]
    public function every_option_label_that_is_a_translation_key_resolves(): void
    {
        // Options are translation keys where one exists and literals otherwise,
        // because a tag is called whatever the forum called it. Only the keys
        // belonging to this extension can be checked here.
        foreach ([new Dimension\VisitorDimension(), new Dimension\RouteDimension()] as $dimension) {
            foreach ($dimension->options() as $option) {
                $path = explode('.', $option['label']);

                $this->assertSame('datlechin-placement', array_shift($path));
                $this->assertSame('admin', array_shift($path));
                $this->assertNotEmpty(self::at($path), "No translation for [{$option['label']}].");
            }
        }
    }
}
