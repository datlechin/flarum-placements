<?php

/*
 * This file is part of datlechin/flarum-placement.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\Placement\Notification;

use Datlechin\Placement\Model\Campaign;
use Flarum\Database\AbstractModel;
use Flarum\Notification\AlertableInterface;
use Flarum\Notification\Blueprint\BlueprintInterface;
use Flarum\User\User;

/**
 * Told when a campaign stops delivering.
 *
 * Worth a notification because a campaign that has quietly reached its cap
 * looks exactly like a campaign that is running: the row still says "active",
 * and the only symptom is that an advertiser's numbers stop moving. Somebody
 * finds out a week later, usually the advertiser.
 *
 * The reason is carried so the notification can say which cap it was, rather
 * than leaving the reader to work it out from a report.
 *
 * `AlertableInterface` is not decoration: the alert driver ignores any
 * blueprint that does not implement it, and does so silently — `sync()`
 * succeeds and no notification is ever written.
 */
class CampaignStoppedBlueprint implements BlueprintInterface, AlertableInterface
{
    public const IMPRESSIONS = 'impressions';
    public const CLICKS = 'clicks';

    public function __construct(
        protected Campaign $campaign,
        protected string $reason,
    ) {
    }

    public function getFromUser(): ?User
    {
        // Nobody sent this. It is the campaign reaching a number it was given.
        return null;
    }

    public function getSubject(): ?AbstractModel
    {
        return $this->campaign;
    }

    public function getData(): mixed
    {
        return ['reason' => $this->reason];
    }

    public static function getType(): string
    {
        return 'datlechinPlacementCampaignStopped';
    }

    public static function getSubjectModel(): string
    {
        return Campaign::class;
    }
}
