<?php

/*
 * This file is part of datlechin/flarum-placement.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\Placement\Targeting\Dimension;

use Datlechin\Placement\Targeting\TargetingContext;
use Flarum\Tags\Tag;

/**
 * The tags on the page being viewed.
 *
 * The dimension that makes forum inventory sellable at all: "the woodworking
 * subforum" is a proposition an advertiser understands, where "your forum" is
 * not.
 *
 * Resolved without a query, from two places and only two:
 *
 * - on a tag listing, the slug is in the route itself;
 * - on a discussion, the tags are already in the API document the page is
 *   about to send, because the discussion header renders them.
 *
 * Deliberately null everywhere else, the discussion *list* included. The tags
 * included alongside a list belong to dozens of different discussions, and
 * treating them as "the tags of this page" would match a campaign targeted at
 * one small tag on the front page of the whole forum.
 */
class TagDimension extends AbstractDimension
{
    public function key(): string
    {
        return 'tag';
    }

    /**
     * @return list<string>|string|null
     */
    public function resolve(TargetingContext $context): array|string|null
    {
        return match ($context->routeName()) {
            'tag' => $context->routeParameter('slug'),
            'discussion' => $context->includedAttribute('tags', 'slug') ?: null,
            default => null,
        };
    }

    public function options(): array
    {
        $options = [];

        /** @var Tag $tag */
        foreach (Tag::query()->orderBy('name')->get() as $tag) {
            $options[] = ['value' => (string) $tag->slug, 'label' => (string) $tag->name];
        }

        return $options;
    }
}
