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

/**
 * Proof that the server actually served this creative, in this slot, a moment
 * ago.
 *
 * Without it, the beacon endpoint is not merely inaccurate, it is a weapon:
 * anybody can post to it until a rival advertiser's cap is exhausted and their
 * campaign is pulled off the forum. Rate limiting does not fix that — a
 * handful of addresses defeats it, and a limit tight enough to matter starts
 * rejecting a university or anything else behind one address.
 *
 * The token says what was served and when, and carries a nonce so the same
 * impression cannot be counted twice. It is not a secret and it is not
 * personal: it names a creative, a slot and a moment, and nothing about who
 * was looking.
 */
final class EventToken
{
    /**
     * How long a token may be presented for.
     *
     * Long enough that a reader can leave a tab open and still have their click
     * counted, short enough that a token scraped from a page is worthless by
     * the time anybody could use it in bulk.
     */
    public const LIFETIME = 1800;

    /**
     * How long a token may be exchanged for a fresh one.
     *
     * Longer than `LIFETIME` because a reading session outlives a token. The
     * two windows do different jobs: `LIFETIME` bounds how long a *report* is
     * accepted, and this bounds how long the browser may keep asking for
     * another chance to report. A token older than this is simply a session
     * that ended.
     */
    public const REFRESH_WINDOW = 86400;

    public const NONCE_BYTES = 8;

    public function __construct(private readonly string $key)
    {
    }

    /**
     * @return array{token: string, nonce: string, issued: int}
     */
    public function issue(int $creativeId, int $campaignId, string $placementKey, ?int $issuedAt = null): array
    {
        $nonce = bin2hex(random_bytes(self::NONCE_BYTES));
        $issued = $issuedAt ?? time();

        return [
            'token' => $this->sign($this->payload($creativeId, $campaignId, $placementKey, $issued, $nonce)),
            'nonce' => $nonce,
            'issued' => $issued,
        ];
    }

    /**
     * Whether a presented token really was issued here, and recently.
     *
     * Returns what the token claims rather than a boolean, because the caller
     * needs the creative and the slot and must not read them from the request
     * body — the whole point is that those values are the signed ones.
     *
     * @return array{creative: int, campaign: int, placement: string, issued: int, nonce: string}|null
     */
    public function verify(string $token, int $creativeId, int $campaignId, string $placementKey, string $nonce, int $issued, ?int $now = null): ?array
    {
        $now ??= time();

        if ($issued > $now + 60 || $now - $issued > self::LIFETIME) {
            return null;
        }

        $expected = $this->sign($this->payload($creativeId, $campaignId, $placementKey, $issued, $nonce));

        // Constant time, so the endpoint does not leak the signature one byte
        // at a time to somebody with a stopwatch.
        if (! hash_equals($expected, $token)) {
            return null;
        }

        return [
            'creative' => $creativeId,
            'campaign' => $campaignId,
            'placement' => $placementKey,
            'issued' => $issued,
            'nonce' => $nonce,
        ];
    }

    /**
     * Exchange a token this server issued for a fresh one for the same advert.
     *
     * A plan is minted once per page load, so in a single-page application one
     * nonce has to cover an entire reading session -- and both the browser and
     * the server refuse a nonce twice. A reader who visits sixty pages was
     * therefore worth one impression per creative, and after half an hour the
     * token expired and every further event was dropped while the adverts kept
     * appearing. The central number was wrong by an order of magnitude.
     *
     * What makes this safe is that nothing is re-decided. The triple is read
     * out of the signature, never out of the request, so a browser can only
     * ever get another token for an advert this server already chose to serve
     * it -- it cannot name a different creative, a different campaign or a
     * different slot. No targeting is re-evaluated, so nothing here can be
     * talked into serving somewhere it should not.
     *
     * @return array{token: string, nonce: string, issued: int}|null
     */
    public function refresh(string $token, int $creativeId, int $campaignId, string $placementKey, string $nonce, int $issued, ?int $now = null): ?array
    {
        $now ??= time();

        if ($issued > $now + 60 || $now - $issued > self::REFRESH_WINDOW) {
            return null;
        }

        $expected = $this->sign($this->payload($creativeId, $campaignId, $placementKey, $issued, $nonce));

        if (! hash_equals($expected, $token)) {
            return null;
        }

        return $this->issue($creativeId, $campaignId, $placementKey, $now);
    }

    private function payload(int $creativeId, int $campaignId, string $placementKey, int $issued, string $nonce): string
    {
        // Pipe-separated with every field present, so no two different
        // decisions can produce the same string. A placement key cannot contain
        // a pipe: the key pattern allows only letters, digits, underscores and
        // dots.
        return implode('|', [$creativeId, $campaignId, $placementKey, $issued, $nonce]);
    }

    private function sign(string $payload): string
    {
        return rtrim(strtr(base64_encode(hash_hmac('sha256', $payload, $this->key, true)), '+/', '-_'), '=');
    }
}
