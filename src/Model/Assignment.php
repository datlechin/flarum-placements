<?php

/*
 * This file is part of datlechin/flarum-placement.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\Placement\Model;

use Flarum\Database\AbstractModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A creative running in one slot.
 *
 * @property int $id
 * @property int $creative_id
 * @property string $placement_key
 * @property int|null $weight
 * @property bool $enabled
 */
class Assignment extends AbstractModel
{
    protected $table = 'placement_assignments';

    protected $casts = [
        'weight' => 'int',
        'enabled' => 'bool',
    ];

    public function creative(): BelongsTo
    {
        return $this->belongsTo(Creative::class);
    }
}
