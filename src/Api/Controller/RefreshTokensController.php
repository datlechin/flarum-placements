<?php

/*
 * This file is part of datlechin/flarum-placements.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\Placements\Api\Controller;

use Datlechin\Placements\Measurement\EventToken;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Fresh proof for adverts this server already chose to serve.
 *
 * A plan is minted once per page load. In a single-page application that means
 * one nonce covers an entire reading session, and both the browser and the
 * server refuse a nonce twice -- so a reader who moved through sixty pages was
 * worth one impression per creative, and after half an hour the token expired
 * and every further event was dropped while the adverts went on appearing.
 *
 * Open to guests for the same reason the beacon is: most readers of a public
 * forum are guests, and refusing them would make every number meaningless.
 * Nothing is re-decided here. Each triple is read out of a signature this
 * server produced, never out of the request body, so a browser can only obtain
 * another token for an advert it was already given -- it cannot name a
 * different creative, campaign or slot, and no targeting is re-evaluated.
 */
class RefreshTokensController implements RequestHandlerInterface
{
    /**
     * A page holds a handful of slots. Far more than that is not a reading
     * session.
     */
    public const MAX_TOKENS = 40;

    public function __construct(protected EventToken $tokens)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $body = $request->getParsedBody();
        $presented = is_array($body) ? ($body['tokens'] ?? null) : null;

        $refreshed = [];

        if (is_array($presented)) {
            foreach (array_slice($presented, 0, self::MAX_TOKENS) as $entry) {
                $fresh = is_array($entry) ? $this->exchange($entry) : null;

                if ($fresh !== null) {
                    $refreshed[] = $fresh;
                }
            }
        }

        return new JsonResponse(['tokens' => $refreshed]);
    }

    /**
     * @param  array<mixed>  $entry
     * @return array{creative: int, campaign: int, placement: string, token: string, nonce: string, issued: int}|null
     */
    protected function exchange(array $entry): ?array
    {
        foreach (['token', 'placement', 'nonce'] as $field) {
            if (! is_string($entry[$field] ?? null) || $entry[$field] === '') {
                return null;
            }
        }

        foreach (['creative', 'campaign', 'issued'] as $field) {
            if (! is_numeric($entry[$field] ?? null)) {
                return null;
            }
        }

        /** @var array{token: string, placement: string, nonce: string, creative: numeric, campaign: numeric, issued: numeric} $entry */
        $creative = (int) $entry['creative'];
        $campaign = (int) $entry['campaign'];

        $fresh = $this->tokens->refresh(
            $entry['token'],
            $creative,
            $campaign,
            $entry['placement'],
            $entry['nonce'],
            (int) $entry['issued'],
        );

        if ($fresh === null) {
            return null;
        }

        // Echoed back so the browser can match each answer to the candidate it
        // asked about. They are the signed values, not the ones it sent: if a
        // request claimed a creative the signature does not cover, the refresh
        // above has already refused it.
        return [
            'creative' => $creative,
            'campaign' => $campaign,
            'placement' => $entry['placement'],
            ...$fresh,
        ];
    }
}
