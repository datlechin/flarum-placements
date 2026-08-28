<?php

/*
 * This file is part of datlechin/flarum-placements.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\Placements\Measurement;

use Flarum\Settings\SettingsRepositoryInterface;

/**
 * The secret event tokens are signed with.
 *
 * Flarum has no application key to borrow, so this extension keeps its own:
 * generated on first use and stored in the settings table.
 *
 * It is deliberately *not* registered through `Extend\Settings`. That extender
 * has no visibility callback at all, so anything it serialises is readable by
 * every guest in view-source — which for a signing key would mean anybody could
 * mint tokens and exhaust a rival advertiser's cap at will. This is read
 * directly from the repository and never leaves the server.
 *
 * Rotating it invalidates tokens still in flight, so a handful of impressions
 * around the rotation go uncounted. That is the correct trade if the key is
 * ever suspected: a lost count is recoverable, a forged one is not.
 */
class SigningKey
{
    public const SETTING = 'datlechin-placements.signing_key';

    public const BYTES = 32;

    private ?string $key = null;

    public function __construct(protected SettingsRepositoryInterface $settings)
    {
    }

    public function get(): string
    {
        if ($this->key !== null) {
            return $this->key;
        }

        $stored = $this->settings->get(self::SETTING);

        if (is_string($stored) && $stored !== '') {
            return $this->key = $stored;
        }

        return $this->key = $this->generate();
    }

    /**
     * Replace the key, invalidating every token still in flight.
     */
    public function rotate(): string
    {
        $this->key = null;

        return $this->generate();
    }

    private function generate(): string
    {
        $key = bin2hex(random_bytes(self::BYTES));

        $this->settings->set(self::SETTING, $key);

        return $this->key = $key;
    }
}
