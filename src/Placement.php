<?php

/*
 * This file is part of datlechin/flarum-placements.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\Placements;

use InvalidArgumentException;

/**
 * One slot on the page that a creative can be served into.
 *
 * A placement is data, not behaviour: it exists because some frontend
 * component renders a slot under its key, and it carries only what the admin
 * UI and the layout need to know about that slot. Everything an administrator
 * can change about it — whether it is on, how many creatives it holds, what it
 * falls back to — lives in the `placement_settings` table under the same key,
 * not here.
 *
 * That split is deliberate. Placements are declared in code and can never be
 * created by an administrator, because Flarum has no server-side templates: a
 * slot exists only if a component renders it. Letting somebody invent
 * `placement_7` in the admin panel produces a slot that never appears, an ad
 * that never shows, and a support ticket nobody can close.
 */
final class Placement
{
    public const GROUP_GLOBAL = 'global';
    public const GROUP_PAGE = 'page';
    public const GROUP_INDEX = 'index';
    public const GROUP_DISCUSSION = 'discussion';
    public const GROUP_USER = 'user';
    public const GROUP_TAGS = 'tags';

    /**
     * Keys reach the database, the frontend payload and CSS class names, so
     * they are restricted to something that is safe in all three. Dotted
     * segments are how a third party namespaces its own: `acme.profile_rail`.
     */
    public const KEY_PATTERN = '/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)*$/';

    /**
     * @param  string  $key  Stable identifier. Written into every assignment row and read by the frontend. Renaming it orphans existing assignments.
     * @param  string  $group  Which part of the forum this slot belongs to, used to group the admin list. One of the `GROUP_*` constants, or your own.
     * @param  string|null  $label  Translation key for the name shown to administrators. Defaults to this extension's own namespace, so third parties must pass their own.
     * @param  string|null  $description  Translation key for the sentence explaining where the slot actually appears. Worth writing: it is what stops "where is this?" tickets.
     * @param  list<string>  $allowedTypes  Creative type keys this slot accepts. Empty means all of them.
     * @param  int  $maxFill  How many creatives may be served here at once, before an administrator overrides it.
     * @param  bool  $repeating  Whether the slot can appear more than once on a page — an ad every Nth post, a row every Nth discussion.
     * @param  array{int, int}|null  $recommendedSize  Width and height in CSS pixels, shown as guidance when uploading a creative.
     * @param  int|null  $reservePhone  Height in CSS pixels to reserve at phone width, so the slot does not shift the layout when it fills. Null means reserve nothing.
     * @param  int|null  $reserveTablet  As above, at tablet width.
     * @param  int|null  $reserveDesktop  As above, at desktop width.
     *
     * @throws InvalidArgumentException If the key is not a legal placement key.
     */
    public function __construct(
        public readonly string $key,
        public readonly string $group = self::GROUP_GLOBAL,
        public readonly ?string $label = null,
        public readonly ?string $description = null,
        public readonly array $allowedTypes = [],
        public readonly int $maxFill = 1,
        public readonly bool $repeating = false,
        public readonly ?array $recommendedSize = null,
        public readonly ?int $reservePhone = null,
        public readonly ?int $reserveTablet = null,
        public readonly ?int $reserveDesktop = null,
    ) {
        if (! preg_match(self::KEY_PATTERN, $key)) {
            throw new InvalidArgumentException(
                "Placement key [$key] must be lowercase letters, digits and underscores, "
                .'optionally namespaced with dots, and must start with a letter.'
            );
        }

        if ($maxFill < 1) {
            throw new InvalidArgumentException("Placement [$key] must hold at least one creative, got [$maxFill].");
        }
    }

    /**
     * Translation key for the placement's name.
     */
    public function labelKey(): string
    {
        return $this->label ?? "datlechin-placements.admin.placements.$this->key.label";
    }

    /**
     * Translation key for the sentence describing where the slot appears.
     */
    public function descriptionKey(): string
    {
        return $this->description ?? "datlechin-placements.admin.placements.$this->key.description";
    }

    /**
     * Whether a creative of this type may be served here.
     */
    public function accepts(string $type): bool
    {
        return $this->allowedTypes === [] || in_array($type, $this->allowedTypes, true);
    }

    /**
     * The reserved heights, keyed by breakpoint, with the breakpoints that
     * reserve nothing left out.
     *
     * @return array<string, int>
     */
    public function reservedHeights(): array
    {
        return array_filter([
            'phone' => $this->reservePhone,
            'tablet' => $this->reserveTablet,
            'desktop' => $this->reserveDesktop,
        ], fn (?int $height) => $height !== null);
    }

    /**
     * The shape the frontend and the admin client receive.
     *
     * @return array{
     *     key: string,
     *     group: string,
     *     label: string,
     *     description: string,
     *     allowedTypes: list<string>,
     *     maxFill: int,
     *     repeating: bool,
     *     recommendedSize: array{int, int}|null,
     *     reserve: array<string, int>
     * }
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'group' => $this->group,
            'label' => $this->labelKey(),
            'description' => $this->descriptionKey(),
            'allowedTypes' => $this->allowedTypes,
            'maxFill' => $this->maxFill,
            'repeating' => $this->repeating,
            'recommendedSize' => $this->recommendedSize,
            'reserve' => $this->reservedHeights(),
        ];
    }
}
