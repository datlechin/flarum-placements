<?php

/*
 * This file is part of datlechin/flarum-placements.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\Placements\Tests\integration;

use Datlechin\Placements\Model\Campaign;
use Datlechin\Placements\Model\Creative;

/**
 * One member with an advert of their own, and one advertiser who is nobody's
 * account.
 *
 * The second exists so that every assertion about forgetting the first can
 * also say what was left alone. Shaped the way `MemberInventory` provisions
 * one: the advertiser and the campaign are both named after the account, and
 * the advertiser carries the account's email address.
 */
trait SeedsMemberAdvertising
{
    /**
     * @return array<string, list<array<string, mixed>>>
     */
    protected function memberAdvertisingSeed(): array
    {
        return [
            'users' => [
                $this->normalUser(),
            ],
            'placement_advertisers' => [
                [
                    'id' => 1,
                    'name' => 'normal',
                    'contact_email' => 'normal@machine.local',
                    'user_id' => 2,
                    'report_token' => 'a-token-somebody-was-sent',
                    'notes' => 'Pays by bank transfer.',
                ],
                [
                    'id' => 2,
                    'name' => 'Cloudforge',
                    'contact_email' => 'ads@cloudforge.example',
                    'user_id' => null,
                ],
            ],
            'placement_campaigns' => [
                [
                    'id' => 1,
                    'advertiser_id' => 1,
                    'name' => 'normal',
                    'status' => Campaign::STATUS_ACTIVE,
                    'tier' => Campaign::TIER_REMNANT,
                    'is_house' => false,
                    'pacing' => Campaign::PACING_ASAP,
                    'frequency_window' => Campaign::WINDOW_DAY,
                    'created_by' => 2,
                    'impressions' => 0,
                    'clicks' => 0,
                ],
                [
                    'id' => 2,
                    'advertiser_id' => 2,
                    'name' => 'Cloudforge annual',
                    'status' => Campaign::STATUS_ACTIVE,
                    'tier' => Campaign::TIER_SPONSORSHIP,
                    'is_house' => false,
                    'pacing' => Campaign::PACING_ASAP,
                    'frequency_window' => Campaign::WINDOW_DAY,
                    // The member reviewed nothing and created nothing here.
                    // It is on their id only so that detaching can be seen to
                    // reach a row that is not their own.
                    'created_by' => 2,
                    'impressions' => 0,
                    'clicks' => 0,
                ],
            ],
            'placement_creatives' => [
                [
                    'id' => 1,
                    'campaign_id' => 1,
                    'name' => 'My banner',
                    'type' => 'image',
                    'status' => Creative::STATUS_APPROVED,
                    'weight' => 10,
                    'destination_url' => 'https://example.com/offer',
                    'payload' => json_encode(['asset' => 'https://example.com/a.png', 'width' => 728, 'height' => 90]),
                    'reviewed_by' => 2,
                    'impressions' => 0,
                    'viewable_impressions' => 0,
                    'clicks' => 0,
                ],
            ],
            'placement_assignments' => [
                ['id' => 1, 'creative_id' => 1, 'placement_key' => 'index_above_list', 'weight' => null, 'enabled' => true],
            ],
            'placement_stats' => [
                [
                    'id' => 1,
                    'bucket_start' => '2026-08-28 14:00:00',
                    'campaign_id' => 1,
                    'creative_id' => 1,
                    'placement_key' => 'index_above_list',
                    'device' => 'desktop',
                    'impressions' => 412,
                    'viewable_impressions' => 300,
                    'clicks' => 5,
                    'filtered' => 0,
                ],
            ],
        ];
    }
}
