<?php

/*
 * This file is part of datlechin/flarum-placement.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\Placement\Frontend;

use Datlechin\Placement\PlacementPlan;
use Flarum\Frontend\Document;
use Flarum\Http\RequestUtil;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Puts this viewer's placement plan into the boot payload.
 *
 * Registered at the default priority, so it runs after core has populated the
 * payload and after the route's own content handler: by the time this is
 * called the actor and their groups are already resolved.
 *
 * The payload key is `placement`, and its absence is meaningful. A viewer who
 * is ad-free, or a crawler, gets nothing written at all, so the frontend has
 * no slots to reserve space for and nothing to report.
 */
class AddPlacementPayload
{
    public function __construct(protected PlacementPlan $plan)
    {
    }

    public function __invoke(Document $document, ServerRequestInterface $request): void
    {
        /** @var array<string, mixed>|null $apiDocument */
        $apiDocument = is_array($document->payload['apiDocument'] ?? null)
            ? $document->payload['apiDocument']
            : null;

        $payload = $this->plan->forActor($request, RequestUtil::getActor($request), $apiDocument);

        if ($payload === null) {
            return;
        }

        $document->payload['placement'] = $payload;
    }
}
