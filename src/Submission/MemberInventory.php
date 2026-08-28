<?php

/*
 * This file is part of datlechin/flarum-placement.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\Placement\Submission;

use Datlechin\Placement\Model\Advertiser;
use Datlechin\Placement\Model\Campaign;
use Flarum\User\User;

/**
 * Where a member's own submissions live.
 *
 * A member who submits is an advertiser, so they get an advertiser row and one
 * campaign under it, both made the first time they submit anything. Nothing is
 * created for somebody who never submits.
 */
class MemberInventory
{
    /**
     * The campaign this member's submissions belong to, creating it if this is
     * their first.
     */
    public function campaignFor(User $actor): Campaign
    {
        $advertiser = $this->advertiserFor($actor);

        $campaign = $advertiser->campaigns()->orderBy('id')->first();

        return $campaign instanceof Campaign ? $campaign : $this->makeCampaign($advertiser, $actor);
    }

    protected function advertiserFor(User $actor): Advertiser
    {
        $advertiser = Advertiser::query()->where('user_id', $actor->id)->first();

        if ($advertiser instanceof Advertiser) {
            return $advertiser;
        }

        // Built explicitly rather than mass-assigned: Flarum's AbstractModel
        // keeps Eloquent's guard on, so `create()` throws on a model with no
        // `$fillable`.
        $advertiser = new Advertiser();
        $advertiser->forceFill([
            'name' => $actor->display_name,
            'contact_email' => $actor->email,
            'user_id' => $actor->id,
        ]);
        $advertiser->save();

        return $advertiser;
    }

    protected function makeCampaign(Advertiser $advertiser, User $actor): Campaign
    {
        $campaign = new Campaign();
        $campaign->forceFill([
            'advertiser_id' => $advertiser->id,
            'name' => $actor->display_name,
            // Active rather than paused, and that is safe because it is not
            // what decides whether anything runs. A creative is served only
            // when it has been approved *and* assigned to a slot, and a member
            // can do neither. Starting the campaign paused would add a third
            // gate that looks like the other two and is turned somewhere else,
            // which is how an administrator ends up approving a creative and
            // wondering why the forum still shows nothing.
            'status' => Campaign::STATUS_ACTIVE,
            // Remnant rather than standard: a member's submission is filled
            // around what the forum sold, not ahead of it.
            'tier' => Campaign::TIER_REMNANT,
            'is_house' => false,
            'pacing' => Campaign::PACING_ASAP,
            'frequency_window' => Campaign::WINDOW_DAY,
            'created_by' => $actor->id,
            'impressions' => 0,
            'clicks' => 0,
        ]);
        $campaign->save();

        return $campaign;
    }
}
