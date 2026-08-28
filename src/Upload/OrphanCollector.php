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

use Datlechin\Placements\Creative\CreativeTypeRegistry;
use Datlechin\Placements\Model\Creative;
use Illuminate\Contracts\Filesystem\Factory;
use Illuminate\Filesystem\FilesystemAdapter;
use RuntimeException;

/**
 * Removes uploaded images nothing points at any more.
 *
 * This is what `CreativeTypeInterface::assets()` was written for. It is
 * documented as the hook that says which files a payload refers to, is
 * implemented by every type, and until now was called from nowhere -- so every
 * replaced banner and every deleted creative left its file behind for good.
 *
 * Two rules keep it from deleting something somebody still wants.
 *
 * A file is only ever a candidate if this extension stored it. A creative may
 * perfectly well point at an advertiser's own CDN, and a URL from anywhere
 * else is not ours to reason about.
 *
 * And a file is only collected once it is older than the grace period.
 * Somebody uploads an image and then spends ten minutes writing the rest of
 * the form; without the grace, a collection running in between would delete
 * the image out from under a form that had not been saved yet.
 */
class OrphanCollector
{
    /**
     * Long enough to fill in a form, go and find the alt text, and come back.
     */
    public const GRACE_HOURS = 24;

    protected FilesystemAdapter $disk;

    public function __construct(
        Factory $filesystem,
        protected CreativeTypeRegistry $types,
        protected CreativeImageUploader $uploader,
    ) {
        $disk = $filesystem->disk(CreativeImageUploader::DISK);

        if (! $disk instanceof FilesystemAdapter) {
            throw new RuntimeException('The creative image disk cannot be listed.');
        }

        $this->disk = $disk;
    }

    /**
     * @param  bool  $dryRun  Report what would go without deleting anything.
     * @return list<string> The names collected, or that would be.
     */
    public function collect(bool $dryRun = false): array
    {
        $referenced = $this->referenced();
        $collected = [];

        foreach ($this->disk->files() as $name) {
            if (isset($referenced[$name])) {
                continue;
            }

            // `lastModified` is seconds; a file the disk cannot date is left
            // alone rather than guessed at.
            $modified = $this->disk->lastModified($name);

            if ($modified > time() - self::GRACE_HOURS * 3600) {
                continue;
            }

            $collected[] = $name;

            if (! $dryRun) {
                $this->disk->delete($name);
            }
        }

        return $collected;
    }

    /**
     * Every stored file name any creative still refers to.
     *
     * Read through each type's own `assets()` rather than by looking for a
     * field called `asset`: a logo wall holds a list of them, and a type an
     * extension added holds whatever it likes.
     *
     * @return array<string, true>
     */
    protected function referenced(): array
    {
        $names = [];

        Creative::query()
            // `id` has to be selected too: `chunkById` pages on it and
            // aborts if it is not in the result.
            ->select(['id', 'type', 'payload'])
            ->chunkById(200, function ($creatives) use (&$names): void {
                foreach ($creatives as $creative) {
                    $type = $this->types->get($creative->type);

                    if ($type === null) {
                        continue;
                    }

                    foreach ($type->assets(is_array($creative->payload) ? $creative->payload : []) as $url) {
                        $name = $this->uploader->nameFor($url);

                        if ($name !== null) {
                            $names[$name] = true;
                        }
                    }
                }
            }, 'id');

        return $names;
    }
}
