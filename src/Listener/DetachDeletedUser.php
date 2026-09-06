<?php

/*
 * This file is part of datlechin/flarum-placements.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\Placements\Listener;

use Datlechin\Placements\Model\Advertiser;
use Datlechin\Placements\Model\Campaign;
use Datlechin\Placements\Model\Creative;
use Flarum\User\Event\Deleted;

/**
 * Three columns in this extension point at a forum account, and nothing was
 * putting them right when the account went away.
 *
 * Flarum writes no database-level foreign keys, so a deleted user leaves the
 * id behind and every `belongsTo` from it starts answering null. Nothing
 * crashes, which is the problem: the rows go on claiming an advertiser is
 * linked to somebody, a campaign was created by somebody and a creative was
 * reviewed by somebody, and the admin panel has no way to say otherwise.
 *
 * Detaching, not deleting. Campaigns already outlive their advertiser on
 * purpose, because the delivery happened and the reports still have to add up.
 * Deleting an account is also not an erasure request: for that, see the GDPR
 * data type, which is what actually removes the person's details.
 *
 * @see \Datlechin\Placements\Model\Advertiser::booted()
 * @see \Datlechin\Placements\Gdpr\AdvertisingData
 */
class DetachDeletedUser
{
    public function handle(Deleted $event): void
    {
        self::detach($event->user->id);
    }

    /**
     * Called directly as well as through the event.
     *
     * `Deleted` is raised on the model but only dispatched by the API resource
     * layer, so an account removed any other way never reaches the listener.
     * flarum/gdpr erases by calling `delete()` on the user itself, which is
     * exactly such a path, and an erasure is the last moment that should be
     * leaving ids behind.
     *
     * @see \Datlechin\Placements\Gdpr\AdvertisingData::delete()
     */
    public static function detach(?int $id): void
    {
        if ($id === null) {
            return;
        }

        // Plain updates rather than loading the models: there is no hook on
        // any of the three that needs to run, and an advertiser with a
        // thousand campaigns should not become a thousand queries.
        Advertiser::query()->where('user_id', $id)->update(['user_id' => null]);
        Campaign::query()->where('created_by', $id)->update(['created_by' => null]);
        Creative::query()->where('reviewed_by', $id)->update(['reviewed_by' => null]);
    }
}
