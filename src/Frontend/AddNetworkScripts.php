<?php

/*
 * This file is part of datlechin/flarum-placements.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\Placements\Frontend;

use Datlechin\Placements\Support\Settings;
use Flarum\Frontend\Document;
use Flarum\Settings\SettingsRepositoryInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Puts the network loader scripts a forum owner nominated into the head.
 *
 * Into `$document->head` and never into the JavaScript bundle. `Extend\Frontend->js()`
 * concatenates every extension into one `forum.js`, so a third-party script
 * there would block the whole forum from rendering until the network answered.
 *
 * `async` on every one of them, for the same reason.
 *
 * Only for viewers who are being served something. A reader whose group is
 * ad-free, or a crawler, gets no plan written for them and gets no network
 * script either — which also means an ad-free forum member is not tracked by a
 * network they cannot see the adverts of.
 */
class AddNetworkScripts
{
    public function __construct(protected SettingsRepositoryInterface $settings)
    {
    }

    public function __invoke(Document $document, ServerRequestInterface $request): void
    {
        // The plan handler runs first and writes nothing at all for a viewer
        // who is being served nothing.
        if (! isset($document->payload['placement'])) {
            return;
        }

        foreach ($this->urls() as $url) {
            $document->head[] = '<script async src="'.htmlspecialchars($url, ENT_QUOTES, 'UTF-8').'"></script>';
        }
    }

    /**
     * One URL per line, http(s) only.
     *
     * Anything else is dropped rather than escaped into the page: a scheme
     * that is not http or https in a `src` is not a loader, it is something
     * else pretending to be one.
     *
     * @return list<string>
     */
    protected function urls(): array
    {
        $stored = $this->settings->get(Settings::NETWORK_SCRIPTS);

        if (! is_string($stored) || trim($stored) === '') {
            return [];
        }

        $urls = [];

        foreach (preg_split('/\R/', $stored) ?: [] as $line) {
            $url = trim($line);

            if ($url !== '' && preg_match('#^https?://#i', $url)) {
                $urls[] = $url;
            }
        }

        return array_values(array_unique($urls));
    }
}
