<?php

/*
 * This file is part of datlechin/flarum-placements.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\Placements\Creative\Type;

use Datlechin\Placements\Model\Creative;

/**
 * A row of sponsor logos, each linking somewhere of its own.
 *
 * The shape a community forum actually sells: not one advert rotating in a
 * slot, but a footer or sidebar block naming everybody who paid this quarter.
 * Done with single-image creatives it needs one slot per sponsor and a stack of
 * assignments to keep in step; done here it is one creative, and adding a
 * sponsor is adding a row.
 *
 * Every logo carries its own destination, so the campaign's own destination is
 * unused. Clicks are still counted: the slot reports on the wrapper, not on
 * each link.
 */
class LogoWallType extends AbstractCreativeType
{
    /**
     * Past this the logos are too small to recognise, which is the only thing
     * a wall of them is for.
     */
    public const MAX_LOGOS = 24;

    public const MAX_COLUMNS = 8;

    public const DEFAULT_COLUMNS = 4;

    public function key(): string
    {
        return 'logo_wall';
    }

    public function label(): string
    {
        return 'datlechin-placements.lib.creatives.types.logo_wall';
    }

    public function rules(): array
    {
        return [
            'logos' => ['required', 'array', 'min:1', 'max:'.self::MAX_LOGOS],
            'logos.*.asset' => ['required', 'string', 'url', 'starts_with:http://,https://'],
            'logos.*.alt' => ['nullable', 'string', 'max:255'],
            'logos.*.url' => ['nullable', 'string', 'url', 'starts_with:http://,https://'],
            'columns' => ['required', 'integer', 'min:1', 'max:'.self::MAX_COLUMNS],
        ];
    }

    public function normalize(array $payload): array
    {
        return [
            'logos' => $this->logos($payload),
            'columns' => $this->columns($payload),
        ];
    }

    public function assets(array $payload): array
    {
        return array_map(fn (array $logo): string => $logo['asset'], $this->logos($payload));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array{asset: string, alt?: string, url?: string}>
     */
    protected function logos(array $payload): array
    {
        $rows = $payload['logos'] ?? null;

        if (! is_array($rows)) {
            return [];
        }

        $logos = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $asset = $this->text($row, 'asset');

            // A row with no image is a row the renderer would draw as a gap.
            // Dropped rather than kept, so that the count the rules check is
            // the count that will actually be drawn.
            if ($asset === null) {
                continue;
            }

            $url = $this->text($row, 'url');

            $logos[] = $this->present([
                'asset' => $asset,
                'alt' => $this->text($row, 'alt'),
                // Checked here as well as by the rules: this is the value that
                // reaches an `href`, and a scheme like `javascript:` makes that
                // script execution rather than navigation.
                'url' => $url !== null && Creative::isAllowedDestination($url) ? $url : null,
            ]);

            if (count($logos) >= self::MAX_LOGOS) {
                break;
            }
        }

        /** @var list<array{asset: string, alt?: string, url?: string}> $logos */
        return $logos;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function columns(array $payload): int
    {
        $columns = $this->integer($payload, 'columns') ?? self::DEFAULT_COLUMNS;

        return max(1, min(self::MAX_COLUMNS, $columns));
    }
}
