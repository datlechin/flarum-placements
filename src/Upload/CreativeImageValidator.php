<?php

/*
 * This file is part of datlechin/flarum-placements.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\Placements\Upload;

use Flarum\Foundation\AbstractImageValidator;

/**
 * What an uploaded creative image has to be.
 *
 * Core's image validator does the work rather than a set of rules written
 * here, and it is worth saying what that buys. It checks the byte size before
 * decoding, so an oversized upload is refused without being read into memory.
 * It refuses a file whose declared pixel dimensions would allocate a huge
 * buffer from a tiny file. It decides what a file *is* by decoding it, not by
 * the name the browser sent -- which is the check that matters, because the
 * name is attacker-controlled and the extension is what a misconfigured server
 * executes. And it refuses anything ending `.php` outright.
 *
 * SVG is absent from the accepted list, in core's default and here. It is a
 * document that can carry script, and it would be served from this forum's own
 * origin.
 */
class CreativeImageValidator extends AbstractImageValidator
{
    /**
     * Core's default list, less `bmp`.
     *
     * A bitmap banner is a mistake rather than a choice: it is uncompressed,
     * so it is enormous, and nothing has needed one on a web page in twenty
     * years. The remaining four are what a sponsor actually sends.
     */
    protected function getAllowedTypes(): array
    {
        return ['jpeg', 'jpg', 'png', 'gif', 'webp'];
    }
}
