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

/**
 * A headline, some words and a call to action.
 *
 * Stored and rendered as text, never as markup: the renderer puts it in text
 * nodes, so there is nothing to escape and nothing to get wrong.
 */
class TextType extends AbstractCreativeType
{
    public function key(): string
    {
        return 'text';
    }

    public function label(): string
    {
        return 'datlechin-placement.admin.creatives.types.text';
    }

    public function rules(): array
    {
        return [
            'headline' => ['required', 'string', 'max:120'],
            'body' => ['nullable', 'string', 'max:400'],
            'cta' => ['nullable', 'string', 'max:60'],
        ];
    }

    public function normalize(array $payload): array
    {
        return $this->present([
            'headline' => $this->text($payload, 'headline'),
            'body' => $this->text($payload, 'body'),
            'cta' => $this->text($payload, 'cta'),
        ]);
    }
}
