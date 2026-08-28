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

use Datlechin\Placements\Creative\CreativeTypeInterface;

/**
 * Reading a payload safely.
 *
 * A payload arrives as decoded JSON, so every value in it is `mixed` until
 * something checks. These helpers do the checking in one place rather than
 * each type casting and hoping — which is how a creative ends up storing
 * `"width": "Array"` and a renderer ends up guessing what to do with it.
 */
abstract class AbstractCreativeType implements CreativeTypeInterface
{
    public function assets(array $payload): array
    {
        return [];
    }

    public function requiredPermission(): ?string
    {
        return null;
    }

    /**
     * A trimmed string, or null when the value is absent, empty, or something
     * that is not a string at all.
     *
     * Keys are read by name, so the array is only required to be an array:
     * a JSON object with a numeric-looking key decodes to an int key, and a
     * payload nested inside another one arrives that way.
     *
     * @param  array<mixed, mixed>  $payload
     */
    protected function text(array $payload, string $key): ?string
    {
        $value = $payload[$key] ?? null;

        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @param  array<mixed, mixed>  $payload
     */
    protected function integer(array $payload, string $key): ?int
    {
        $value = $payload[$key] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * Drop the keys that resolved to nothing, so a renderer never has to tell
     * "absent" apart from "explicitly nothing".
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function present(array $payload): array
    {
        return array_filter($payload, fn (mixed $value) => $value !== null);
    }
}
