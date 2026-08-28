<?php

/*
 * This file is part of datlechin/flarum-placements.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\Placements\Support;

use Flarum\User\User;
use Illuminate\Contracts\Session\Session;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Shows every slot on the forum filled with a labelled sample, to the person
 * who switched it on and to nobody else.
 *
 * This is the highest-return feature in the whole extension and it is
 * deliberately built first rather than last. Flarum has no server-side
 * templates, so placements are invisible: there is no file an administrator
 * can open to see where "index sidebar" actually is. "Where is this slot?" and
 * "why is my ad not showing?" are going to be most of the support this
 * extension ever generates, and both are answerable in five seconds with this
 * and close to unanswerable without it.
 *
 * Borrowed from phpBB's ad management extension, which is the only forum
 * software that got this right.
 */
final class DemoMode
{
    /**
     * `?placement_demo=1` switches it on, `=0` switches it off. Anything else
     * leaves it alone, so the flag survives navigation without having to be
     * carried in every link.
     */
    public const PARAM = 'placement_demo';

    public const SESSION_KEY = 'datlechin-placements.demo';

    /**
     * Whether this actor may use demo mode at all.
     *
     * `hasPermission()` is the right call here, unlike in
     * {@see Permissions::isAdFree()}: an administrator genuinely should be able
     * to preview slots, and its administrator short-circuit is doing exactly
     * what one would want.
     */
    public static function isAvailableTo(User $actor): bool
    {
        return $actor->hasPermission(Permissions::MANAGE);
    }

    /**
     * Work out whether demo mode should be on after this request.
     *
     * Pure, so the rule is testable without a session: the query parameter
     * wins when it says something, and the stored state carries over when it
     * does not.
     */
    public static function nextState(?string $param, bool $stored): bool
    {
        if ($param === null || $param === '') {
            return $stored;
        }

        return filter_var($param, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $stored;
    }

    /**
     * Read the demo flag for this request, updating the session when the query
     * parameter asked to change it.
     *
     * Returns false for anybody who may not use demo mode, whatever the
     * session says: a permission can be revoked while a session is still open.
     */
    public static function forRequest(ServerRequestInterface $request, User $actor): bool
    {
        if (! self::isAvailableTo($actor)) {
            return false;
        }

        $session = $request->getAttribute('session');
        $session = $session instanceof Session ? $session : null;

        $stored = (bool) $session?->get(self::SESSION_KEY, false);

        $params = $request->getQueryParams();
        $param = is_array($params) ? ($params[self::PARAM] ?? null) : null;
        $next = self::nextState(is_string($param) ? $param : null, $stored);

        // `put` and `forget`, not `set` and `remove`: the session Flarum puts
        // on the request is an Illuminate\Session\Store, whose contract has no
        // `set()` at all.
        if ($next !== $stored) {
            $next ? $session?->put(self::SESSION_KEY, true) : $session?->forget(self::SESSION_KEY);
        }

        return $next;
    }
}
