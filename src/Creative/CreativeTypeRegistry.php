<?php

/*
 * This file is part of datlechin/flarum-placements.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\Placements\Creative;

use Illuminate\Contracts\Container\Container;
use RuntimeException;

/**
 * Every kind of creative the forum knows how to store.
 *
 * Resolved lazily: a type registered by an extension nobody has switched on
 * costs nothing until somebody tries to author one.
 */
class CreativeTypeRegistry
{
    /**
     * @var array<string, CreativeTypeInterface>
     */
    private array $resolved = [];

    /**
     * @param  list<class-string<CreativeTypeInterface>>  $types
     */
    public function __construct(
        protected Container $container,
        protected array $types = [],
    ) {
    }

    /**
     * @return array<string, CreativeTypeInterface>
     */
    public function all(): array
    {
        foreach ($this->types as $class) {
            /** @var CreativeTypeInterface $type */
            $type = $this->container->make($class);

            if (isset($this->resolved[$type->key()]) && $this->resolved[$type->key()]::class !== $class) {
                throw new RuntimeException(
                    "Creative type [{$type->key()}] is claimed by both ".$this->resolved[$type->key()]::class." and $class."
                );
            }

            $this->resolved[$type->key()] = $type;
        }

        return $this->resolved;
    }

    public function has(string $key): bool
    {
        return isset($this->all()[$key]);
    }

    public function get(string $key): ?CreativeTypeInterface
    {
        return $this->all()[$key] ?? null;
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->all());
    }

    /**
     * The types an actor may actually author.
     *
     * A type may require a permission of its own — raw HTML does, because it
     * runs as same-origin JavaScript on every page of the forum, including the
     * one an administrator is looking at.
     *
     * @param  callable(string): bool  $can
     * @return array<string, CreativeTypeInterface>
     */
    public function availableTo(callable $can): array
    {
        return array_filter($this->all(), function (CreativeTypeInterface $type) use ($can) {
            $permission = $type->requiredPermission();

            return $permission === null || $can($permission);
        });
    }
}
