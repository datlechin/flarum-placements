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
 * `filter[q]=acme` over the advertiser list.
 *
 * The contact address is searched as well as the name, because an
 * administrator chasing "who is billing@acme.example" has the address in front
 * of them and rarely the name the record was filed under. Only somebody who
 * may already list every advertiser can ask, so nothing is exposed by it that
 * the same request would not have returned anyway.
 */
class AdvertiserTextFilter extends AbstractTextFilter
{
    protected function columns(): array
    {
        return ['name', 'contact_email'];
    }
}
