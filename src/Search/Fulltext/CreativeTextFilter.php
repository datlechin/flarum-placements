<?php

/*
 * This file is part of datlechin/flarum-placements.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\Placements\Search\Fulltext;

/**
 * `filter[q]=banner` over the creative list.
 *
 * The payload is not searched. It holds raw HTML for one type and a network's
 * container attributes for another, so matching against it would turn a search
 * for "script" into a list of every creative that happens to embed one.
 */
class CreativeTextFilter extends AbstractTextFilter
{
    protected function columns(): array
    {
        return ['name'];
    }
}
