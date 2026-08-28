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

/**
 * A picture with a link. The ordinary case, and the one that needs no trust at
 * all: an image cannot execute anything.
 */
class ImageType extends AbstractCreativeType
{
    public function key(): string
    {
        return 'image';
    }

    public function label(): string
    {
        return 'datlechin-placements.admin.creatives.types.image';
    }

    public function rules(): array
    {
        return [
            'asset' => ['required', 'string', 'url', 'starts_with:http://,https://'],
            'alt' => ['nullable', 'string', 'max:255'],
            // Not required, but strongly wanted: without them the slot cannot
            // hold space open and the page jumps when the image loads.
            'width' => ['nullable', 'integer', 'min:1', 'max:4000'],
            'height' => ['nullable', 'integer', 'min:1', 'max:4000'],
        ];
    }

    public function normalize(array $payload): array
    {
        return $this->present([
            'asset' => $this->text($payload, 'asset'),
            'alt' => $this->text($payload, 'alt'),
            'width' => $this->integer($payload, 'width'),
            'height' => $this->integer($payload, 'height'),
        ]);
    }

    public function assets(array $payload): array
    {
        $asset = $this->text($payload, 'asset');

        return $asset === null ? [] : [$asset];
    }
}
