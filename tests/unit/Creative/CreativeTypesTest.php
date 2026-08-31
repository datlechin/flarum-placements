<?php

/*
 * This file is part of datlechin/flarum-placements.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\Placements\Tests\unit\Creative;

use Datlechin\Placements\Creative\Type\ImageType;
use Datlechin\Placements\Creative\Type\NetworkType;
use Datlechin\Placements\Creative\Type\RawHtmlType;
use Datlechin\Placements\Creative\Type\TextType;
use Datlechin\Placements\Support\Permissions;
use Flarum\Foundation\Config;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The types that carry no dependency of their own.
 *
 * RichTextType is not here: it renders through Flarum's formatter, and a
 * formatter stubbed well enough to test against would only be testing the
 * stub. It is covered in integration instead.
 */
class CreativeTypesTest extends TestCase
{
    /**
     * `Config` refuses to be built without a `url`, so every case here has to
     * carry one whether or not it is looking at it.
     *
     * @param  array<string, mixed>  $config
     */
    private static function config(array $config): Config
    {
        return new Config(['url' => 'https://forum.example', ...$config]);
    }

    #[Test]
    public function image_keeps_the_fields_it_knows(): void
    {
        $payload = (new ImageType())->normalize([
            'asset' => '  https://cdn.example/a.png ',
            'alt' => ' Acme ',
            'width' => '728',
            'height' => 90,
            'somethingElse' => 'dropped',
        ]);

        $this->assertSame([
            'asset' => 'https://cdn.example/a.png',
            'alt' => 'Acme',
            'width' => 728,
            'height' => 90,
        ], $payload);
    }

    /**
     * A renderer should never have to tell "no alt text" apart from
     * "alt text that is an empty string".
     */
    #[Test]
    public function image_drops_the_keys_that_resolved_to_nothing(): void
    {
        $payload = (new ImageType())->normalize(['asset' => 'https://cdn.example/a.png', 'alt' => '  ', 'width' => 'wide']);

        $this->assertSame(['asset' => 'https://cdn.example/a.png'], $payload);
    }

    #[Test]
    public function image_reports_its_asset(): void
    {
        $this->assertSame(['https://cdn.example/a.png'], (new ImageType())->assets(['asset' => 'https://cdn.example/a.png']));
        $this->assertSame([], (new ImageType())->assets([]));
    }

    #[Test]
    public function text_keeps_the_fields_it_knows(): void
    {
        $payload = (new TextType())->normalize(['headline' => ' Buy things ', 'body' => 'Cheaply.', 'cta' => '']);

        $this->assertSame(['headline' => 'Buy things', 'body' => 'Cheaply.'], $payload);
    }

    /**
     * The safe types are the ones a member may submit, so gating them behind a
     * permission of their own would leave the submit portal with nothing to
     * offer.
     */
    #[Test]
    public function the_safe_types_need_no_permission_of_their_own(): void
    {
        $this->assertNull((new ImageType())->requiredPermission());
        $this->assertNull((new TextType())->requiredPermission());
    }

    /**
     * Both of these put author-controlled code on every page of the forum: one
     * as pasted markup, the other as a script the network serves.
     */
    #[Test]
    public function the_dangerous_types_need_the_narrower_permission(): void
    {
        $this->assertSame(Permissions::AUTHOR_HTML, (new RawHtmlType(self::config([])))->requiredPermission());
        $this->assertSame(Permissions::AUTHOR_HTML, (new NetworkType())->requiredPermission());
    }

    /**
     * Recorded on the creative rather than decided at render time, so that
     * turning the config flag off later cannot promote an old creative out of
     * its sandbox.
     */
    #[Test]
    public function raw_html_always_records_that_it_is_sandboxed(): void
    {
        $payload = (new RawHtmlType(self::config([])))->normalize(['html' => '<b>hi</b>', 'height' => 120, 'sandbox' => false]);

        $this->assertTrue($payload['sandbox']);
    }

    #[Test]
    #[DataProvider('configsThatDoNotEnableRawHtml')]
    public function raw_html_is_off_unless_config_php_says_otherwise(array $config): void
    {
        $this->assertFalse((new RawHtmlType(self::config($config)))->isEnabled());
    }

    public static function configsThatDoNotEnableRawHtml(): array
    {
        return [
            'nothing at all' => [[]],
            'the section but not the flag' => [['datlechin-placements' => []]],
            'the flag switched off' => [['datlechin-placements' => ['raw_html' => false]]],
            // Deliberately strict. A truthy string is how somebody ends up
            // enabling this by pasting a value from somewhere else.
            'a truthy string rather than true' => [['datlechin-placements' => ['raw_html' => '1']]],
            'the section is not a section' => [['datlechin-placements' => 'yes']],
        ];
    }

    #[Test]
    public function raw_html_is_on_when_config_php_says_so(): void
    {
        $this->assertTrue((new RawHtmlType(self::config(['datlechin-placements' => ['raw_html' => true]])))->isEnabled());
    }

    /**
     * The attributes are rendered as real attributes on a real element, so the
     * names that may reach there are restricted to what a network's own
     * documentation uses.
     */
    #[Test]
    public function network_keeps_only_the_attribute_names_a_network_uses(): void
    {
        $payload = (new NetworkType())->normalize([
            'element' => 'ins',
            'attributes' => [
                'data-ad-client' => 'ca-pub-1',
                'class' => 'adsbygoogle',
                'id' => 'unit-1',
                'style' => 'display:block',
                'onerror' => 'alert(1)',
                'srcdoc' => '<script>alert(1)</script>',
                'href' => 'javascript:alert(1)',
                'DATA-Mixed-Case' => 'kept',
            ],
        ]);

        $this->assertSame([
            'data-ad-client' => 'ca-pub-1',
            'class' => 'adsbygoogle',
            'id' => 'unit-1',
            'style' => 'display:block',
            'DATA-Mixed-Case' => 'kept',
        ], $payload['attributes']);
    }

    #[Test]
    public function network_falls_back_to_a_div_for_an_element_it_does_not_know(): void
    {
        $this->assertSame('div', (new NetworkType())->normalize(['element' => 'script', 'attributes' => []])['element']);
        $this->assertSame('div', (new NetworkType())->normalize(['attributes' => []])['element']);
        $this->assertSame('ins', (new NetworkType())->normalize(['element' => 'ins', 'attributes' => []])['element']);
    }

    /**
     * The defaults are the cautious ones in both directions: consent is
     * required unless the forum owner says it is not, and an element is never
     * re-requested on navigation unless they say it may be.
     */
    #[Test]
    public function network_defaults_to_requiring_consent_and_not_refreshing(): void
    {
        $payload = (new NetworkType())->normalize(['element' => 'div', 'attributes' => []]);

        $this->assertTrue($payload['requiresConsent']);
        $this->assertFalse($payload['refreshOnNavigate']);
    }

    #[Test]
    public function network_takes_only_a_real_boolean_to_change_those(): void
    {
        $type = new NetworkType();

        $this->assertTrue($type->normalize(['attributes' => [], 'requiresConsent' => 'false'])['requiresConsent']);
        $this->assertFalse($type->normalize(['attributes' => [], 'refreshOnNavigate' => '1'])['refreshOnNavigate']);

        $this->assertFalse($type->normalize(['attributes' => [], 'requiresConsent' => false])['requiresConsent']);
        $this->assertTrue($type->normalize(['attributes' => [], 'refreshOnNavigate' => true])['refreshOnNavigate']);
    }

    /**
     * A creative type's name reaches both frontends, so it has to be a `lib.`
     * key.
     *
     * `ForumFields` ships `placementSubmittableTypes` to anybody holding the
     * SUBMIT permission, and the submission form on the forum renders each
     * label. Under `admin` those labels were never sent there -- Flarum keeps
     * only `<extension>.<frontend>.` and `<extension>.lib.` in a frontend's
     * bundle -- so a member choosing what to submit was offered a list reading
     * "datlechin-placements.admin.creatives.types.image".
     *
     * The literal-scanning guard in the JS tests cannot see this: the key is
     * built in PHP and arrives in a payload.
     */
    #[Test]
    #[DataProvider('everyType')]
    public function a_type_is_named_in_a_namespace_both_frontends_are_served(string $class): void
    {
        // RawHtmlType is the only one that takes a dependency, and what it
        // reads from the config has no bearing on its name.
        $type = $class === RawHtmlType::class ? new RawHtmlType(self::config([])) : new $class();
        $key = $type->label();

        $this->assertStringStartsWith(
            'datlechin-placements.lib.',
            $key,
            "Creative type [{$type->key()}] names [$key], which the forum frontend is not served."
        );

        /** @var array<string, mixed> $locale */
        $locale = \Symfony\Component\Yaml\Yaml::parseFile(__DIR__.'/../../../locale/en.yml')['datlechin-placements'];

        $value = $locale;
        foreach (array_slice(explode('.', $key), 1) as $segment) {
            $value = is_array($value) && array_key_exists($segment, $value) ? $value[$segment] : null;
        }

        $this->assertNotEmpty($value, "Creative type [{$type->key()}] has no label at [$key].");
    }

    /**
     * @return list<array{class-string}>
     */
    public static function everyType(): array
    {
        return [
            [ImageType::class],
            [TextType::class],
            [NetworkType::class],
            [RawHtmlType::class],
            [\Datlechin\Placements\Creative\Type\LogoWallType::class],
        ];
    }
}
