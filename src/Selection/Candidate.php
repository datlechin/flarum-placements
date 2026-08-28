<?php

/*
 * This file is part of datlechin/flarum-placement.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\Placement\Selection;

/**
 * One creative that survived the server's filtering, ready for the client to
 * pick from.
 *
 * The server does not choose. It hands over everything this viewer is eligible
 * to see, and the browser makes the final draw — because the last few inputs
 * only exist there: the viewport class, the viewer's own clock, and how often
 * they have already seen a given creative. Doing it this way also means a slot
 * paints in the same frame as the page rather than after a round trip.
 *
 * What it costs is that eligible inventory is visible in the page source. That
 * is an acceptable trade for house ads and direct-sold campaigns, and it is
 * the reason anything commercially sensitive has to be decided on the server.
 */
final readonly class Candidate
{
    /**
     * @param  int  $tier  Lower is more important. Only the best non-empty tier is ever drawn from.
     * @param  int  $weight  Relative share within the tier.
     * @param  array<string, mixed>  $payload  Shape owned by the creative type.
     * @param  array{token: string, nonce: string, issued: int}|null  $token  Proof this server served this creative here, a moment ago.
     */
    public function __construct(
        public int $creativeId,
        public int $campaignId,
        public int $tier,
        public int $weight,
        public string $type,
        public array $payload,
        public ?string $url = null,
        public ?string $label = null,
        public ?array $token = null,
        public ?int $frequencyCap = null,
        public string $frequencyWindow = 'day',
    ) {
    }

    /**
     * @return array{
     *     creative: int,
     *     campaign: int,
     *     tier: int,
     *     weight: int,
     *     type: string,
     *     payload: array<string, mixed>,
     *     url: string|null,
     *     label: string|null,
     *     token: string|null,
     *     nonce: string|null,
     *     issued: int|null
     * }
     */
    public function toArray(): array
    {
        return [
            'creative' => $this->creativeId,
            'campaign' => $this->campaignId,
            'tier' => $this->tier,
            'weight' => $this->weight,
            'type' => $this->type,
            'payload' => $this->payload,
            'url' => $this->url,
            'label' => $this->label,
            'token' => $this->token['token'] ?? null,
            'nonce' => $this->token['nonce'] ?? null,
            'issued' => $this->token['issued'] ?? null,
            // The frequency rule travels with the creative because the browser
            // enforces it: how often this reader has already seen something is
            // only knowable there.
            'cap' => $this->frequencyCap,
            'window' => $this->frequencyWindow,
        ];
    }
}
