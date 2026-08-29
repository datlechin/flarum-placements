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
 * `filter[q]=summer` over the campaign list.
 *
 * Only the name. The contract notes are searched nowhere on purpose: they are
 * where somebody writes what a sponsor is paying and who agreed it, and a
 * search box is the wrong place for that to surface.
 */
class CampaignTextFilter extends AbstractTextFilter
{
    protected function columns(): array
    {
        return ['name'];
    }
}
