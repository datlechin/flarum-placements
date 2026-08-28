<?php

/*
 * This file is part of datlechin/flarum-placements.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\Placements\Targeting;

use Flarum\User\User;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Everything a dimension is allowed to know about the current page view.
 *
 * Dimensions receive this rather than a raw request so that the expensive
 * lookups happen once here and are shared, and so that adding a new source of
 * context later does not change the signature every third-party dimension
 * implements.
 *
 * Nothing in here queries. The route was resolved by middleware before this
 * request reached us, and the API document was already assembled to render the
 * page — reading the tags of the discussion being viewed costs nothing because
 * they are sitting in the payload the browser is about to receive anyway.
 */
final class TargetingContext
{
    /**
     * @var array<string, mixed>|null
     */
    private ?array $routeParameters = null;

    /**
     * @param  array<string, mixed>|null  $apiDocument  The page's JSON:API document, as it already sits in the frontend payload.
     */
    public function __construct(
        public readonly ServerRequestInterface $request,
        public readonly User $actor,
        public readonly ?array $apiDocument = null,
    ) {
    }

    /**
     * The name of the matched route, as `ResolveRoute` recorded it.
     */
    public function routeName(): ?string
    {
        $name = $this->request->getAttribute('routeName');

        return is_string($name) ? $name : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function routeParameters(): array
    {
        if ($this->routeParameters === null) {
            $parameters = $this->request->getAttribute('routeParameters');

            $this->routeParameters = is_array($parameters) ? $parameters : [];
        }

        return $this->routeParameters;
    }

    public function routeParameter(string $name): ?string
    {
        $value = $this->routeParameters()[$name] ?? null;

        return is_scalar($value) ? (string) $value : null;
    }

    public function isGuest(): bool
    {
        return $this->actor->isGuest();
    }

    /**
     * Resources of a given type that the page's API document already carries.
     *
     * Only useful where the document is known to be *about* one thing — the
     * tags included alongside a single discussion are that discussion's tags,
     * whereas the tags included alongside a discussion list belong to dozens of
     * different discussions and mean nothing collectively. Callers are
     * responsible for knowing which situation they are in.
     *
     * @return list<array<string, mixed>>
     */
    public function included(string $type): array
    {
        $included = $this->apiDocument['included'] ?? null;

        if (! is_array($included)) {
            return [];
        }

        $matching = [];

        foreach ($included as $resource) {
            if (is_array($resource) && ($resource['type'] ?? null) === $type) {
                $matching[] = $resource;
            }
        }

        return $matching;
    }

    /**
     * An attribute plucked off every included resource of a type, with the
     * ones that do not have it left out.
     *
     * @return list<string>
     */
    public function includedAttribute(string $type, string $attribute): array
    {
        $values = [];

        foreach ($this->included($type) as $resource) {
            $attributes = $resource['attributes'] ?? null;

            if (! is_array($attributes)) {
                continue;
            }

            $value = $attributes[$attribute] ?? null;

            if (is_scalar($value)) {
                $values[] = (string) $value;
            }
        }

        return array_values(array_unique($values));
    }
}
