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

use RuntimeException;

/**
 * Every placement the forum knows about, built once and resolved from the
 * container.
 *
 * The registry is the single answer to "does this slot exist?", which is asked
 * in three places that must agree: the admin UI listing what can be sold, the
 * assignment editor validating a creative's target, and the frontend deciding
 * whether a payload entry has anywhere to render. A key that is not in here
 * is not a placement, however many database rows mention it.
 */
final class PlacementRegistry
{
    /**
     * @var array<string, Placement>
     */
    private array $placements = [];

    /**
     * Accepts `mixed` elements on purpose: what arrives here comes out of a
     * container binding that any extension may have appended to, so the shape
     * is checked rather than assumed. Getting a clear sentence beats a
     * TypeError raised four frames deeper.
     *
     * @param  iterable<mixed>  $placements
     *
     * @throws RuntimeException If anything in the list is not a placement.
     */
    public function __construct(iterable $placements = [])
    {
        foreach ($placements as $placement) {
            if (! $placement instanceof Placement) {
                throw new RuntimeException(
                    'Expected a '.Placement::class.', got ['.get_debug_type($placement).']. '
                    .'Placements are value objects rather than class strings, so pass '
                    .'`->placement(new Placement(key: ...))` rather than `->placement(SomeClass::class)`.'
                );
            }

            $this->add($placement);
        }
    }

    /**
     * @throws RuntimeException If something already claimed this key.
     */
    public function add(Placement $placement): void
    {
        if (isset($this->placements[$placement->key])) {
            throw new RuntimeException(
                "Placement [$placement->key] is already registered. Two extensions cannot claim the same "
                .'placement key; namespace yours, for example [acme.'.$placement->key.'].'
            );
        }

        $this->placements[$placement->key] = $placement;
    }

    public function has(string $key): bool
    {
        return isset($this->placements[$key]);
    }

    public function get(string $key): ?Placement
    {
        return $this->placements[$key] ?? null;
    }

    /**
     * @throws RuntimeException If no placement is registered under this key.
     */
    public function getOrFail(string $key): Placement
    {
        return $this->get($key) ?? throw new RuntimeException(
            "Placement [$key] is not registered. Registered placements are: ".implode(', ', $this->keys()).'.'
        );
    }

    /**
     * @return array<string, Placement>
     */
    public function all(): array
    {
        return $this->placements;
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->placements);
    }

    public function count(): int
    {
        return count($this->placements);
    }

    /**
     * The placements arranged the way the admin list renders them.
     *
     * @return array<string, list<Placement>>
     */
    public function grouped(): array
    {
        $grouped = [];

        foreach ($this->placements as $placement) {
            $grouped[$placement->group][] = $placement;
        }

        return $grouped;
    }

    /**
     * Only the placements that accept a creative of the given type.
     *
     * @return array<string, Placement>
     */
    public function accepting(string $type): array
    {
        return array_filter($this->placements, fn (Placement $p) => $p->accepts($type));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function toArray(): array
    {
        return array_values(array_map(fn (Placement $p) => $p->toArray(), $this->placements));
    }
}
