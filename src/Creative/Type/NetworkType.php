<?php

/*
 * This file is part of datlechin/flarum-placement.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\Placement\Creative\Type;

use Datlechin\Placement\Support\Permissions;

/**
 * A container for something an external network fills.
 *
 * Described by what it mechanically is — a loader script in the head, and a
 * container element with some attributes on it — rather than named after a
 * vendor. That is not squeamishness. No Flarum contributor has ever confirmed
 * that serving a particular network from a single-page application is
 * compliant with that network's policies, and the failure mode is not a broken
 * slot, it is the forum owner's account being closed.
 *
 * So this extension supplies the two things every previous Flarum ads
 * extension got right, and nothing beyond them:
 *
 * - a script the forum owner nominates, loaded once, asynchronously, in the
 *   head;
 * - a container element with whatever attributes the network's own
 *   documentation says to put on it.
 *
 * What it deliberately does *not* do is re-initialise the container when the
 * reader navigates. Flarum is a single-page application, so a route change
 * destroys and rebuilds the container — and re-requesting an advert at that
 * moment is exactly the behaviour whose permissibility nobody has established.
 * `refreshOnNavigate` exists so a forum owner who has read their network's
 * policy can turn it on; it is off until they do.
 */
class NetworkType extends AbstractCreativeType
{
    public function key(): string
    {
        return 'network';
    }

    public function label(): string
    {
        return 'datlechin-placement.admin.creatives.types.network';
    }

    public function rules(): array
    {
        return [
            // The element the network's snippet asks for, e.g. `ins` or `div`.
            'element' => ['required', 'string', 'in:ins,div'],
            // Its attributes, exactly as the network's documentation gives
            // them. Kept as data rather than as a pasted snippet so they can be
            // rendered as real attributes on a real element rather than
            // through `innerHTML`.
            'attributes' => ['required', 'array', 'max:20'],
            'attributes.*' => ['string', 'max:200'],
            'height' => ['nullable', 'integer', 'min:1', 'max:2000'],
            'refreshOnNavigate' => ['nullable', 'boolean'],
            'requiresConsent' => ['nullable', 'boolean'],
        ];
    }

    public function normalize(array $payload): array
    {
        $attributes = [];

        if (is_array($payload['attributes'] ?? null)) {
            foreach ($payload['attributes'] as $name => $value) {
                // Attribute names are restricted to what a network's own
                // documentation actually uses. It also means nothing can
                // smuggle in an `onerror` or a `srcdoc`.
                if (is_string($name) && preg_match('/^(data-[a-z0-9-]+|class|id|style)$/i', $name) && is_scalar($value)) {
                    $attributes[$name] = (string) $value;
                }
            }
        }

        return $this->present([
            'element' => in_array($payload['element'] ?? null, ['ins', 'div'], true) ? $payload['element'] : 'div',
            'attributes' => $attributes,
            'height' => $this->integer($payload, 'height'),
            // Off unless somebody deliberately turned it on, having read their
            // network's policy on refreshing an element without the reader
            // asking for it.
            'refreshOnNavigate' => ($payload['refreshOnNavigate'] ?? false) === true,
            // On by default: the forum owner has to say a network needs no
            // consent, rather than having to remember that it does.
            'requiresConsent' => ($payload['requiresConsent'] ?? true) !== false,
        ]);
    }

    /**
     * Nominating a script that runs on every page of the forum is the same
     * class of decision as pasting markup, so it takes the same permission.
     */
    public function requiredPermission(): ?string
    {
        return Permissions::AUTHOR_HTML;
    }
}
