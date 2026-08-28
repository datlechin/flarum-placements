<?php

/*
 * This file is part of datlechin/flarum-placement.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\Placement\Console;

use Datlechin\Placement\Measurement\Recorder;
use Flarum\Console\AbstractCommand;

/**
 * Moves buffered counts into the database.
 *
 * Scheduled every minute, and also run opportunistically on one beacon request
 * in fifty — because a large share of self-hosted Flarum installs never added
 * `* * * * * php flarum schedule:run`, and statistics that simply never appear
 * are a worse failure than a small write on an occasional request.
 */
class FlushStatsCommand extends AbstractCommand
{
    public function __construct(protected Recorder $recorder)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('placement:flush')
            ->setDescription('Write buffered advertising statistics to the database');
    }

    protected function fire(): int
    {
        $written = $this->recorder->flush();

        $this->info($written === 0 ? 'Nothing buffered.' : "Wrote $written bucket(s).");

        return 0;
    }
}
