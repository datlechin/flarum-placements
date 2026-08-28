<?php

/*
 * This file is part of datlechin/flarum-placement.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\Placement\Api\Controller;

use Datlechin\Placement\Measurement\EventToken;
use Datlechin\Placement\Measurement\Recorder;
use Datlechin\Placement\Model\Stat;
use Laminas\Diactoros\Response\EmptyResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Where the browser reports what it actually showed.
 *
 * Open to guests by necessity — most readers of a public forum are guests, and
 * refusing to count them would make every number meaningless. What keeps it
 * from being a stats-poisoning endpoint is that each event must present a
 * token this server signed, naming the creative and the slot; the body's own
 * claims about those are never trusted.
 *
 * Always answers 204, whatever it decided about the events. A beacon is fired
 * with `sendBeacon` from a page that may be closing: nothing is listening for
 * a reply, and telling an attacker which of their forged tokens were accepted
 * would be a favour to nobody.
 */
class RecordEventsController implements RequestHandlerInterface
{
    /**
     * More than a page can legitimately produce, and small enough that a
     * malformed body cannot cost anything.
     */
    public const MAX_EVENTS = 50;

    /**
     * How often a request also flushes the buffer to the database.
     *
     * The scheduled command is the real mechanism, but a large share of
     * self-hosted Flarum installs never added `schedule:run`, and statistics
     * that simply never appear are worse than a small write on one request in
     * fifty.
     */
    public const FLUSH_CHANCE = 50;

    public function __construct(
        protected EventToken $tokens,
        protected Recorder $recorder,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $body = $request->getParsedBody();
        $events = is_array($body) ? ($body['events'] ?? null) : null;

        if (is_array($events)) {
            foreach (array_slice($events, 0, self::MAX_EVENTS) as $event) {
                if (is_array($event)) {
                    $this->handleEvent($event);
                }
            }
        }

        if (random_int(1, self::FLUSH_CHANCE) === 1) {
            $this->recorder->flush();
        }

        return new EmptyResponse(204);
    }

    /**
     * @param  array<mixed>  $event
     */
    protected function handleEvent(array $event): void
    {
        $type = $event['type'] ?? null;

        if (! is_string($type) || ! Stat::isKnownType($type) || $type === Stat::FILTERED) {
            // `filtered` is a verdict this server reaches, never something a
            // client may claim about itself.
            return;
        }

        $claims = $this->verify($event);

        if ($claims === null) {
            return;
        }

        // One count per event per nonce. An impression and a click share a
        // token — the click has to prove the impression was served — so they
        // are claimed separately.
        if (! $this->recorder->claim($claims['nonce'], $type)) {
            return;
        }

        $this->recorder->record([
            'creative' => $claims['creative'],
            'campaign' => $claims['campaign'],
            'placement' => $claims['placement'],
            'device' => $this->device($event),
        ], $type);
    }

    /**
     * @param  array<mixed>  $event
     * @return array{creative: int, campaign: int, placement: string, nonce: string}|null
     */
    protected function verify(array $event): ?array
    {
        foreach (['token', 'placement', 'nonce'] as $field) {
            if (! is_string($event[$field] ?? null) || $event[$field] === '') {
                return null;
            }
        }

        foreach (['creative', 'campaign', 'issued'] as $field) {
            if (! is_numeric($event[$field] ?? null)) {
                return null;
            }
        }

        /** @var array{token: string, placement: string, nonce: string, creative: numeric, campaign: numeric, issued: numeric} $event */
        $claims = $this->tokens->verify(
            $event['token'],
            (int) $event['creative'],
            (int) $event['campaign'],
            $event['placement'],
            $event['nonce'],
            (int) $event['issued'],
        );

        if ($claims === null) {
            return null;
        }

        return [
            'creative' => $claims['creative'],
            'campaign' => $claims['campaign'],
            'placement' => $claims['placement'],
            'nonce' => $claims['nonce'],
        ];
    }

    /**
     * Unsigned, because the server cannot know it: viewport class is decided
     * in the browser. Misreporting it misfiles a count between two columns and
     * moves no cap, so an unrecognised value is simply recorded as unknown
     * rather than rejected.
     *
     * @param  array<mixed>  $event
     */
    protected function device(array $event): string
    {
        $device = $event['device'] ?? null;

        return in_array($device, ['phone', 'tablet', 'desktop'], true) ? $device : '';
    }
}
