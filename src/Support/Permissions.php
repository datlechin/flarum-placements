<?php

/*
 * This file is part of datlechin/flarum-placement.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\Placement\Support;

use Flarum\User\User;

/**
 * The permissions this extension defines, and the one place that decides who
 * is ad-free.
 */
final class Permissions
{
    /**
     * Hides every placement from the people who hold it. The most-requested
     * capability in nine years of Flarum ads discussion.
     */
    public const VIEW_WITHOUT_ADS = 'datlechin-placement.viewWithoutAds';

    /**
     * Create and edit campaigns and creatives from the forum side, without
     * being a full administrator.
     */
    public const MANAGE = 'datlechin-placement.manage';

    /**
     * Submit a creative for review. Members only ever author the safe types.
     */
    public const SUBMIT = 'datlechin-placement.submit';

    /**
     * Author a creative carrying raw HTML, which runs as same-origin
     * JavaScript on every page of the forum. Deliberately separate from
     * MANAGE, and useless on its own: the `config.php` flag has to be set too.
     */
    public const AUTHOR_HTML = 'datlechin-placement.authorHtml';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [self::VIEW_WITHOUT_ADS, self::MANAGE, self::SUBMIT, self::AUTHOR_HTML];
    }

    /**
     * Whether this actor should be served no placements at all.
     *
     * Note what this does *not* call. `User::hasPermission()` returns true for
     * every administrator, unconditionally — see User.php, the `isAdmin()`
     * short-circuit at the top of it. Using it here would make every
     * administrator permanently ad-free, which is wrong twice over: it is not
     * what "grant ad-free browsing to this group" means, and it would leave the
     * one person who needs to check that the ads work unable to see them.
     *
     * `getPermissions()` returns only what was actually granted to the actor's
     * groups, with no bypass, so an administrator is ad-free exactly when they
     * ticked the box for a group they are in.
     */
    public static function isAdFree(User $actor): bool
    {
        return self::wasGranted($actor->getPermissions(), self::VIEW_WITHOUT_ADS);
    }

    /**
     * Whether a permission appears in a set of granted permissions, ignoring
     * any administrator bypass.
     *
     * @param  array<string>  $granted  Typically `$actor->getPermissions()`.
     */
    public static function wasGranted(array $granted, string $permission): bool
    {
        return in_array($permission, $granted, true);
    }
}
