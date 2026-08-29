<?php

/*
 * This file is part of datlechin/flarum-placements.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\Placements\Api\Controller;

use Carbon\Carbon;
use Datlechin\Placements\Model\Stat;
use Datlechin\Placements\Support\Permissions;
use Datlechin\Placements\Support\Settings;
use Datlechin\Placements\Upload\OrphanCollector;
use Flarum\Http\RequestUtil;
use Flarum\Settings\SettingsRepositoryInterface;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * What the two settings that delete things are actually holding.
 *
 * "Keep statistics for 90 days" is a number an administrator has no way to
 * judge. It does not say how much is stored, how far back the history goes, or
 * whether lowering it would throw away anything they still wanted. Nor does
 * anything in the browser say that the setting does nothing on its own: it is
 * the scheduled prune that acts on it, and a forum with no scheduler keeps
 * every bucket for ever while the field cheerfully reads "90".
 *
 * So this answers the questions the field raises, and the settings screen shows
 * the answers next to it.
 *
 * The counts are computed on request rather than cached. It is one screen, read
 * by one person, and a cached figure that disagreed with the prune would be
 * worse than no figure at all.
 */
class StorageController implements RequestHandlerInterface
{
    public function __construct(
        protected SettingsRepositoryInterface $settings,
        protected OrphanCollector $orphans,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        RequestUtil::getActor($request)->assertCan(Permissions::MANAGE);

        $days = Settings::retentionDays($this->settings);

        // The same cutoff `placements:prune` computes, so that "would be
        // deleted" here and what the command deletes are the same rows rather
        // than two nearly-equal answers.
        $cutoff = Carbon::now()->utc()->startOfHour()->subDays($days);

        return new JsonResponse([
            'retentionDays' => $days,
            'buckets' => Stat::query()->count(),
            'expiring' => Stat::query()->where('bucket_start', '<', $cutoff)->count(),
            'oldest' => $this->oldestBucket(),
            // A dry run: this reports, it never deletes. Deleting is the
            // command's job, and doing it from a GET would mean a page refresh
            // could throw away somebody's images.
            'orphanedImages' => count($this->orphans->collect(true)),
        ]);
    }

    /**
     * When the history starts, or null when nothing has been recorded yet.
     */
    protected function oldestBucket(): ?string
    {
        $oldest = Stat::query()->min('bucket_start');

        // Drivers disagree about what a datetime aggregate comes back as --
        // a string on most, already a date on others -- and an empty table
        // gives null. Anything that is not a string it can parse is reported
        // as "no history", which is what an empty table means anyway.
        if (! is_string($oldest) || $oldest === '') {
            return null;
        }

        return Carbon::parse($oldest)->toIso8601String();
    }
}
