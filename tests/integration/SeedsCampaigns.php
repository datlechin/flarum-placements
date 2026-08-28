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
 * One live campaign with one approved image creative, assigned to the slot
 * above the discussion list.
 *
 * Written as raw rows rather than through the API, so that a test of the
 * serving path does not depend on the admin endpoints working.
 */
trait SeedsCampaigns
{
    /**
     * @return array<string, list<array<string, mixed>>>
     */
    protected function campaignSeed(): array
    {
        return [
            'placement_campaigns' => [
                [
                    'id' => 1,
                    'name' => 'Acme',
                    'status' => Campaign::STATUS_ACTIVE,
                    'tier' => Campaign::TIER_STANDARD,
                    'is_house' => false,
                    'pacing' => Campaign::PACING_ASAP,
                    'frequency_window' => Campaign::WINDOW_DAY,
                    'impressions' => 0,
                    'clicks' => 0,
                ],
            ],
            'placement_creatives' => [
                [
                    'id' => 1,
                    'campaign_id' => 1,
                    'name' => 'Acme leaderboard',
                    'type' => 'image',
                    'status' => Creative::STATUS_APPROVED,
                    'weight' => 10,
                    'destination_url' => 'https://example.com/offer',
                    'payload' => json_encode(['asset' => 'https://example.com/a.png', 'width' => 728, 'height' => 90]),
                    'impressions' => 0,
                    'viewable_impressions' => 0,
                    'clicks' => 0,
                ],
            ],
            'placement_assignments' => [
                [
                    'id' => 1,
                    'creative_id' => 1,
                    'placement_key' => 'index_above_list',
                    'weight' => null,
                    'enabled' => true,
                ],
            ],
        ];
    }
}
