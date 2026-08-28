<?php

/*
 * This file is part of datlechin/flarum-placements.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\Placements\Targeting\Dimension;

use Datlechin\Placements\Targeting\DimensionInterface;

/**
 * Sensible defaults for a dimension: set membership, decided on the server,
 * named from this extension's own locale bundle.
 */
abstract class AbstractDimension implements DimensionInterface
{
    public function label(): string
    {
        return "datlechin-placements.admin.dimensions.{$this->key()}.label";
    }

    public function operators(): array
    {
        return [self::IS, self::IS_NOT];
    }

    public function isServerSide(): bool
    {
        return true;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function options(): array
    {
        return [];
    }
}
