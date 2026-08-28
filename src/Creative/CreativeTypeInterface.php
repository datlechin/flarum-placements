<?php

/*
 * This file is part of datlechin/flarum-placements.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\Placements\Creative;

/**
 * A kind of creative, and the rules for the payload it stores.
 *
 * Creatives are typed rather than being a box you paste HTML into, and that
 * one decision is what makes the rest of the extension possible. A textarea
 * cannot be validated, previewed, measured, lazily loaded or consent-gated,
 * and Mithril's `m.trust()` will not execute a `<script>` inside one at all —
 * which is the limitation every previous Flarum ads extension documented and
 * none of them solved. Knowing that a creative *is* an AdSense unit is what
 * lets us give it a fresh keyed `<ins>` per decision and push it exactly once.
 *
 * Implementations are resolved from the container, so they may type-hint what
 * they need.
 */
interface CreativeTypeInterface
{
    /**
     * Stable identifier, stored in `creatives.type` and matched against a
     * frontend renderer registered under the same key.
     */
    public function key(): string;

    /**
     * Translation key for the name shown when choosing a creative type.
     */
    public function label(): string;

    /**
     * Laravel validation rules for the payload, keyed by payload field.
     *
     * These run on save, so a creative that reaches the database is a creative
     * the renderer can render. Rules are relative to the payload, not to the
     * request: return `['headline' => 'required|string|max:80']`, not
     * `['payload.headline' => ...]`.
     *
     * @return array<string, mixed>
     */
    public function rules(): array;

    /**
     * Put a validated payload into its canonical shape before it is stored.
     *
     * This is where a URL gets its scheme checked, a size is derived from an
     * uploaded file, or markdown is rendered and cached alongside its source.
     * Anything expensive belongs here rather than on the serving path, which
     * runs on every page view.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function normalize(array $payload): array;

    /**
     * The asset identifiers this payload refers to.
     *
     * Used to work out which uploaded files are still in use, so the ones that
     * are not can be collected. A type that stores no files returns an empty
     * array.
     *
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    public function assets(array $payload): array;

    /**
     * A permission the actor must hold to author a creative of this type, or
     * null when being able to manage creatives at all is enough.
     *
     * This exists for one reason: a creative that carries arbitrary HTML runs
     * as same-origin JavaScript on every page of the forum, including the page
     * an administrator is looking at. That is not a convenience feature, it is
     * privilege escalation with a delivery mechanism, and it needs a narrower
     * gate than "can edit ads".
     */
    public function requiredPermission(): ?string;
}
