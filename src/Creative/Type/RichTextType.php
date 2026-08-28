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

use Flarum\Formatter\Formatter;
use s9e\TextFormatter\Utils;

/**
 * Copy written in the forum's own formatting.
 *
 * Between TextType, which cannot carry a link inside a sentence, and
 * RawHtmlType, which can carry anything at all and has to be sandboxed for it.
 * An advertiser wanting a bold word and a link in their paragraph needed one of
 * the two extremes; this is the middle.
 *
 * It goes through Flarum's own formatter rather than a markdown library of its
 * own, for three reasons: the syntax is then whatever the forum's own posts
 * use, so nobody has to learn a second one; the output is safe by construction,
 * because it is generated from a parse tree rather than filtered from HTML; and
 * a forum that has switched markdown off gets a type that behaves the way the
 * rest of the forum does instead of one that contradicts it.
 *
 * Rendered once, here, and stored beside its source. Changing which formatting
 * extensions are enabled does not re-render creatives already stored -- saving
 * one again does.
 */
class RichTextType extends AbstractCreativeType
{
    /**
     * Long enough for a paragraph of copy, short enough that a slot cannot
     * become an article.
     */
    public const MAX_SOURCE = 2000;

    public function __construct(protected Formatter $formatter)
    {
    }

    public function key(): string
    {
        return 'rich_text';
    }

    public function label(): string
    {
        return 'datlechin-placements.admin.creatives.types.rich_text';
    }

    public function rules(): array
    {
        return [
            'source' => ['required', 'string', 'max:'.self::MAX_SOURCE],
        ];
    }

    public function normalize(array $payload): array
    {
        $source = $this->text($payload, 'source') ?? '';

        // Parsed only at a size the rules will accept. normalize() runs before
        // validation, so without this a megabyte pasted into the box would be
        // put through the formatter on its way to being rejected.
        if ($source === '' || mb_strlen($source) > self::MAX_SOURCE) {
            return ['source' => $source];
        }

        $xml = $this->formatter->parse($source);

        return [
            'source' => $source,
            'html' => $this->formatter->render($this->markLinksAsPaid($xml)),
            // Whether the slot may wrap the whole creative in the campaign's
            // destination link. Decided here, where the parse tree can be asked
            // directly, rather than by looking for `<a` in rendered markup.
            //
            // Copy that already contains a link must not be wrapped in another:
            // nested anchors are invalid, and browsers recover from them by
            // closing the outer one wherever they feel like it.
            'wrappable' => Utils::getAttributeValues($xml, 'URL', 'url') === [],
        ];
    }

    /**
     * Put `sponsored` on every link before the formatter fills in its own
     * defaults.
     *
     * `configureDefaultsOnLinks()` assigns `rel` with `??=`, so a value already
     * present survives. Its default of `ugc nofollow` is right for a post and
     * wrong here: every link in a paid creative is a paid link, and a pattern
     * of those passing PageRank earns an unnatural-outbound-links action
     * against the whole forum, which costs more than the advertising is worth.
     */
    protected function markLinksAsPaid(string $xml): string
    {
        return Utils::replaceAttributes($xml, 'URL', function (array $attributes): array {
            $attributes['rel'] = 'sponsored nofollow noopener';
            $attributes['target'] = '_blank';

            return $attributes;
        });
    }
}
