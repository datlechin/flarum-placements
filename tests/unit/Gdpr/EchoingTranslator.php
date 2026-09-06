<?php

/*
 * This file is part of datlechin/flarum-placements.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\Placements\Tests\unit\Gdpr;

use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Answers every request with the key it was asked for.
 *
 * Which is what lets a test assert the key a class builds without booting a
 * forum and without depending on which locale files that forum loaded.
 */
class EchoingTranslator implements TranslatorInterface
{
    /**
     * @param array<string, mixed> $parameters
     */
    public function trans(?string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
    {
        return (string) $id;
    }

    public function getLocale(): string
    {
        return 'en';
    }
}
