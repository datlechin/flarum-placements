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

use Datlechin\Placements\Creative\Type\LogoWallType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class LogoWallTypeTest extends TestCase
{
    private LogoWallType $type;

    protected function setUp(): void
    {
        parent::setUp();

        $this->type = new LogoWallType();
    }

    #[Test]
    public function it_keeps_a_complete_row(): void
    {
        $payload = $this->type->normalize([
            'logos' => [['asset' => 'https://cdn.example/a.png', 'alt' => 'Acme', 'url' => 'https://acme.example']],
            'columns' => 3,
        ]);

        $this->assertSame([
            'logos' => [['asset' => 'https://cdn.example/a.png', 'alt' => 'Acme', 'url' => 'https://acme.example']],
            'columns' => 3,
        ], $payload);
    }

    #[Test]
    public function it_trims_the_values_in_a_row(): void
    {
        $payload = $this->type->normalize([
            'logos' => [['asset' => '  https://cdn.example/a.png  ', 'alt' => '  Acme  ']],
        ]);

        $this->assertSame([['asset' => 'https://cdn.example/a.png', 'alt' => 'Acme']], $payload['logos']);
    }

    /**
     * A row with no image would render as a gap, and would still be counted by
     * the `min`/`max` rules, so the count checked is not the count drawn.
     */
    #[Test]
    public function it_drops_a_row_with_no_image(): void
    {
        $payload = $this->type->normalize([
            'logos' => [
                ['alt' => 'No image here'],
                ['asset' => 'https://cdn.example/b.png'],
                ['asset' => '   '],
            ],
        ]);

        $this->assertSame([['asset' => 'https://cdn.example/b.png']], $payload['logos']);
    }

    #[Test]
    public function it_drops_a_row_that_is_not_a_row(): void
    {
        $payload = $this->type->normalize([
            'logos' => ['https://cdn.example/a.png', 42, null, ['asset' => 'https://cdn.example/b.png']],
        ]);

        $this->assertSame([['asset' => 'https://cdn.example/b.png']], $payload['logos']);
    }

    #[Test]
    public function it_drops_logos_that_is_not_a_list(): void
    {
        $this->assertSame([], $this->type->normalize(['logos' => 'https://cdn.example/a.png'])['logos']);
        $this->assertSame([], $this->type->normalize([])['logos']);
    }

    /**
     * This value reaches an `href`, and `javascript:` there is script
     * execution rather than navigation. The rules reject it too; this is the
     * second of the two checks, because normalize output is what gets stored.
     */
    #[Test]
    #[DataProvider('unsafeDestinations')]
    public function it_refuses_an_unsafe_destination_while_keeping_the_logo(string $url): void
    {
        $payload = $this->type->normalize([
            'logos' => [['asset' => 'https://cdn.example/a.png', 'url' => $url]],
        ]);

        $this->assertSame([['asset' => 'https://cdn.example/a.png']], $payload['logos']);
    }

    public static function unsafeDestinations(): array
    {
        return [
            'javascript' => ['javascript:alert(1)'],
            'data' => ['data:text/html,<script>alert(1)</script>'],
            'no scheme at all' => ['acme.example'],
            'a protocol-relative address' => ['//acme.example'],
        ];
    }

    #[Test]
    public function it_stops_at_the_maximum_number_of_logos(): void
    {
        $rows = array_map(
            fn (int $i) => ['asset' => "https://cdn.example/$i.png"],
            range(1, LogoWallType::MAX_LOGOS + 5)
        );

        $payload = $this->type->normalize(['logos' => $rows]);

        $this->assertCount(LogoWallType::MAX_LOGOS, $payload['logos']);
        $this->assertSame('https://cdn.example/1.png', $payload['logos'][0]['asset']);
    }

    #[Test]
    #[DataProvider('columnCounts')]
    public function it_holds_the_column_count_in_range(mixed $given, int $expected): void
    {
        $this->assertSame($expected, $this->type->normalize(['columns' => $given])['columns']);
    }

    public static function columnCounts(): array
    {
        return [
            'in range' => [3, 3],
            'as a string, which is how a form sends it' => ['5', 5],
            'absent' => [null, LogoWallType::DEFAULT_COLUMNS],
            'not a number' => ['many', LogoWallType::DEFAULT_COLUMNS],
            'below one' => [0, 1],
            'negative' => [-4, 1],
            'above the maximum' => [99, LogoWallType::MAX_COLUMNS],
        ];
    }

    #[Test]
    public function it_reports_every_image_as_an_asset(): void
    {
        $assets = $this->type->assets([
            'logos' => [
                ['asset' => 'https://cdn.example/a.png'],
                ['alt' => 'dropped, no image'],
                ['asset' => 'https://cdn.example/b.png'],
            ],
        ]);

        $this->assertSame(['https://cdn.example/a.png', 'https://cdn.example/b.png'], $assets);
    }

    #[Test]
    public function it_needs_no_permission_of_its_own(): void
    {
        // Every value in the payload is either an image address or text, and
        // neither can execute anything -- unlike raw HTML, which can.
        $this->assertNull($this->type->requiredPermission());
    }
}
