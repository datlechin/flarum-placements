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
use Datlechin\Placements\Upload\OrphanCollector;
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
 * It also collects uploaded images nothing refers to any more, which is the
 * other thing here that only grows. Both are the same job — deleting what is
 * no longer needed — and a forum owner should not have to know about two
 * commands to keep the disk from filling.
 *
 * A forum with no scheduler never runs this. That is survivable here in a way
 * it is not for campaign expiry — the table grows, but nothing serves wrongly
 * — which is why liveness is computed and retention is not.
 */
class PruneStatsCommand extends AbstractCommand
{
    public const DEFAULT_DAYS = Settings::DEFAULT_RETENTION_DAYS;

    public function __construct(
        protected SettingsRepositoryInterface $settings,
        protected OrphanCollector $orphans,
    ) {
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
            ->addOption('days', null, InputOption::VALUE_REQUIRED, 'How many days to keep, overriding the setting')
            ->addOption('keep-images', null, InputOption::VALUE_NONE, 'Leave uploaded images that nothing refers to')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report what would go without deleting anything');
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
        $deleted = (bool) $this->input->getOption('dry-run')
            ? 0
            : Stat::query()->where('bucket_start', '<', $cutoff)->getQuery()->delete();

        $dryRun = (bool) $this->input->getOption('dry-run');

        if ($dryRun) {
            $deleted = Stat::query()->where('bucket_start', '<', $cutoff)->getQuery()->count();

            $this->info("Would delete $deleted bucket(s) older than {$cutoff->toDateString()}.");
        } else {
            $this->info("Deleted $deleted bucket(s) older than {$cutoff->toDateString()}.");
        }

        if (! $this->input->getOption('keep-images')) {
            $orphans = $this->orphans->collect($dryRun);
            $verb = $dryRun ? 'Would delete' : 'Deleted';

            $this->info(sprintf('%s %d uploaded image(s) nothing refers to.', $verb, count($orphans)));
        }

        return 0;
    }
}
