<?php

/*
 * This file is part of datlechin/flarum-placements.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\Placements\Creative\Type;

use Datlechin\Placements\Support\Permissions;
use Flarum\Foundation\Config;

/**
 * Markup somebody pasted in.
 *
 * This is privilege escalation with a delivery mechanism, and it is treated as
 * such. A creative of this type is rendered inside
 * `<iframe sandbox="allow-scripts" srcdoc>`, which is the only thing that
 * actually contains it — and never with `allow-same-origin` alongside, because
 * a same-origin sandboxed frame can remove its own `sandbox` attribute.
 *
 * Without the sandbox it would run as same-origin JavaScript on every page of
 * the forum, including the one an administrator is looking at. Flarum renders
 * the CSRF token into the boot payload in plain text, so one `getElementById`
 * is the whole chain from "pasted an advert" to "posts to the API as whoever
 * is reading".
 *
 * So it needs two keys turned at once: a permission of its own, distinct from
 * being able to manage advertising at all, *and* a flag in `config.php` that
 * no web request can set. An administrator account that is compromised or
 * careless can turn one of them; it cannot turn both.
 *
 * Blocking `<script>` in the pasted text is not a substitute and is not
 * attempted. `<img onerror>` and `<svg onload>` do the same job, and a filter
 * that catches some of them teaches everybody that the field is safe.
 */
class RawHtmlType extends AbstractCreativeType
{
    /**
     * `datlechin-placements.raw_html` in `config.php`.
     */
    public const CONFIG_KEY = 'datlechin-placements';

    public const CONFIG_FLAG = 'raw_html';

    public function __construct(protected Config $config)
    {
    }

    public function key(): string
    {
        return 'raw_html';
    }

    public function label(): string
    {
        return 'datlechin-placements.lib.creatives.types.raw_html';
    }

    /**
     * Whether the forum owner has opened this door at all.
     *
     * In `config.php`, which is a file on disk rather than a row a web request
     * can write:
     *
     * ```php
     * 'datlechin-placements' => ['raw_html' => true],
     * ```
     */
    public function isEnabled(): bool
    {
        $section = $this->config[self::CONFIG_KEY] ?? null;

        return is_array($section) && ($section[self::CONFIG_FLAG] ?? false) === true;
    }

    public function rules(): array
    {
        return [
            'html' => ['required', 'string', 'max:20000'],
            // Height cannot be inferred from inside a sandboxed frame without
            // letting it talk to the page, so it is declared. A frame with no
            // height is a frame nobody sees.
            'height' => ['required', 'integer', 'min:1', 'max:2000'],
        ];
    }

    public function normalize(array $payload): array
    {
        return [
            'html' => $this->text($payload, 'html') ?? '',
            'height' => $this->integer($payload, 'height') ?? 250,
            // Recorded on the creative rather than decided at render time, so
            // that turning the config flag off later cannot silently promote
            // an old creative out of its sandbox.
            'sandbox' => true,
        ];
    }

    public function requiredPermission(): ?string
    {
        return Permissions::AUTHOR_HTML;
    }
}
