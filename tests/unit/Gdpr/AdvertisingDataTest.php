<?php

/*
 * This file is part of datlechin/flarum-placements.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\Placements\Tests\unit\Gdpr;

use Datlechin\Placements\Gdpr\AdvertisingData;
use Illuminate\Container\Container;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The three sentences flarum/gdpr prints next to its own data types.
 *
 * They are the one part of this integration nothing else would catch. The
 * parent builds all three keys under `flarum-gdpr.lib.data.<type>.*`, which is
 * a namespace this extension cannot write into, so all three are overridden
 * here -- and a key that is overridden but never added to the locale file
 * fails silently, by printing itself at an administrator.
 *
 * Asserted against the file rather than through a booted forum on purpose: an
 * integration test cannot tell a missing key from a translator that has not
 * loaded this extension's locale, and in Flarum's own test app it has not.
 */
class AdvertisingDataTest extends TestCase
{
    public static function descriptions(): array
    {
        return [
            'export' => ['exportDescription'],
            'anonymize' => ['anonymizeDescription'],
            'delete' => ['deleteDescription'],
        ];
    }

    #[Test]
    #[DataProvider('descriptions')]
    public function every_description_names_a_key_the_locale_file_defines(string $method): void
    {
        // A translator that answers with the key it was asked for, so the
        // assertion is about the key the class builds rather than about
        // whatever a forum happens to have loaded.
        $key = self::withEchoingTranslator(fn () => AdvertisingData::$method());

        $this->assertStringStartsWith('datlechin-placements.', $key);
        $this->assertIsString(self::lookUp($key), "$key is not in locale/en.yml");
    }

    #[Test]
    public function the_type_is_not_named_after_advertising(): void
    {
        // It is printed in the GDPR data type list and used to name the folder
        // in the export. Every public string in this extension says
        // "placements" instead, for the same reason the asset paths do.
        $this->assertSame('Placements', AdvertisingData::dataType());
    }

    /**
     * Walks a dotted key into the parsed locale file.
     */
    private static function lookUp(string $key): mixed
    {
        /** @var array<string, mixed> $node */
        $node = Yaml::parseFile(__DIR__.'/../../../locale/en.yml');

        foreach (explode('.', $key) as $segment) {
            if (! is_array($node) || ! array_key_exists($segment, $node)) {
                return null;
            }

            $node = $node[$segment];
        }

        return $node;
    }

    private static function withEchoingTranslator(callable $callback): string
    {
        $previous = Container::getInstance();

        $container = new Container();
        $container->instance(TranslatorInterface::class, new EchoingTranslator());

        Container::setInstance($container);

        try {
            return $callback();
        } finally {
            Container::setInstance($previous);
        }
    }
}
