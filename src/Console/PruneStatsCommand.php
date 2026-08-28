<?php

/*
 * This file is part of datlechin/flarum-placements.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\Placements\Console;

use Carbon\Carbon;
use Datlechin\Placements\Model\Stat;
use Datlechin\Placements\Support\Settings;
use Flarum\Console\AbstractCommand;
use Flarum\Settings\SettingsRepositoryInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * Drops hourly buckets older than the retention window.
 *
 * Hourly detail is what makes a slow afternoon visible; a year later nobody
 * needs it by the hour, and a table that only grows is a problem on exactly
 * the shared-hosting installs least able to notice.
 *
 * A forum with no scheduler never runs this. That is survivable here in a way
 * it is not for campaign expiry — the table grows, but nothing serves wrongly
 * — which is why liveness is computed and retention is not.
 */
class PruneStatsCommand extends AbstractCommand
{
    public const DEFAULT_DAYS = Settings::DEFAULT_RETENTION_DAYS;

    public function __construct(protected SettingsRepositoryInterface $settings)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('placements:prune')
            ->setDescription('Delete advertising statistics older than the retention window')
            // No default. Absent means "whatever the forum was told to keep",
            // which is the admin panel's "Keep statistics for" field; a default
            // here would quietly outrank it, which is what it used to do.
            ->addOption('days', null, InputOption::VALUE_REQUIRED, 'How many days to keep, overriding the setting');
    }

    protected function fire(): int
    {
        $option = $this->input->getOption('days');

        $days = is_numeric($option)
            ? (int) $option
            : Settings::retentionDays($this->settings);

        if ($days < 1) {
            $this->error('Keep at least one day.');

            return 1;
        }

        $cutoff = Carbon::now()->utc()->startOfHour()->subDays($days);

        // The underlying query builder rather than Eloquent's: its `delete()`
        // is typed as returning the row count, and there are no model events
        // on a statistics row worth dispatching a few thousand of.
        $deleted = Stat::query()->where('bucket_start', '<', $cutoff)->getQuery()->delete();

        $this->info("Deleted $deleted bucket(s) older than {$cutoff->toDateString()}.");

        return 0;
    }
}
