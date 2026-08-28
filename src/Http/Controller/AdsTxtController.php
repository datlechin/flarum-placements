<?php

/*
 * This file is part of datlechin/flarum-placements.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\Placements\Http\Controller;

use Datlechin\Placements\Support\Settings;
use Flarum\Settings\SettingsRepositoryInterface;
use Laminas\Diactoros\Response\TextResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Serves `/ads.txt`.
 *
 * The one file in this extension whose name is not ours to choose: it is fixed
 * by the IAB specification, and a network that cannot find it at exactly that
 * path treats the inventory as unauthorised. It is also the reason this is a
 * route rather than a file on disk — a forum owner should not have to have
 * shell access to authorise a seller.
 *
 * Answers 404 while it is empty, which is what the specification asks for:
 * an empty `ads.txt` means "nobody is authorised", and serving one by accident
 * would stop every network buying the forum's inventory.
 */
class AdsTxtController implements RequestHandlerInterface
{
    public function __construct(protected SettingsRepositoryInterface $settings)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $contents = $this->settings->get(Settings::ADS_TXT);

        if (! is_string($contents) || trim($contents) === '') {
            return new TextResponse('', 404);
        }

        return new TextResponse(
            // Normalised to Unix line endings: the specification is
            // line-oriented, and a file pasted from Windows otherwise ends up
            // with a stray carriage return on every record.
            str_replace(["\r\n", "\r"], "\n", trim($contents))."\n",
            200,
            [
                'Content-Type' => 'text/plain; charset=utf-8',
                // Crawled rather than read, and rarely changed. An hour is
                // long enough to be cheap and short enough that adding a
                // seller takes effect the same day.
                'Cache-Control' => 'public, max-age=3600',
            ]
        );
    }
}
