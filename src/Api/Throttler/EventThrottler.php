<?php

/*
 * This file is part of datlechin/flarum-placement.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\Placement\Api\Throttler;

use Illuminate\Contracts\Cache\Repository as Cache;
use Psr\Http\Message\ServerRequestInterface;

/**
 * A ceiling on how often one address may report events.
 *
 * Not a security control &mdash; the signed token is that, and a throttle tight
 * enough to stop a determined attacker would reject a university, an office or
 * a household behind one address long before it stopped them. This exists so
 * that a runaway script cannot fill the buffer, and nothing more.
 *
 * Deliberately generous for the same reason: on a public forum most readers
 * are guests behind shared addresses, and refusing to count them would make
 * every number meaningless.
 */
class EventThrottler
{
    public const WINDOW = 60;

    /**
     * Requests, not events. Each carries a batch, and the batch is capped
     * separately by the controller.
     */
    public const LIMIT = 120;

    public function __construct(protected Cache $cache)
    {
    }

    public function __invoke(ServerRequestInterface $request): ?bool
    {
        if ($request->getAttribute('routeName') !== 'datlechin-placement.events') {
            // Null rather than false: this throttler has no opinion about any
            // other route, and returning false would exempt them from
            // everybody else's throttles.
            return null;
        }

        $ip = $request->getAttribute('ipAddress');
        $key = 'datlechin-placement.throttle.'.sha1(is_string($ip) ? $ip : 'unknown');

        $seen = $this->cache->get($key);
        $count = is_numeric($seen) ? (int) $seen : 0;

        if ($count >= self::LIMIT) {
            return true;
        }

        // `add` then `increment`, so the window starts at the first request of
        // the minute rather than being pushed forward by every one after it.
        $this->cache->add($key, 0, self::WINDOW);
        $this->cache->increment($key);

        return null;
    }
}
