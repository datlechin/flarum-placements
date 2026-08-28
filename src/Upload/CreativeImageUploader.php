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

use Illuminate\Contracts\Filesystem\Factory;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Str;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;

/**
 * Puts a validated image on disk and says where it went.
 *
 * The stored name is generated, never taken from the upload. A filename is
 * attacker-controlled, and one that keeps its own extension is how a file
 * that passed an image check gets served as something else by a
 * misconfigured server.
 */
class CreativeImageUploader
{
    public const DISK = 'datlechin-placements-creatives';

    /**
     * The concrete adapter, not the `Filesystem` contract: `url()` is what
     * this class exists to call, and the contract does not declare it.
     */
    protected FilesystemAdapter $disk;

    public function __construct(Factory $filesystem)
    {
        $disk = $filesystem->disk(self::DISK);

        if (! $disk instanceof FilesystemAdapter) {
            throw new RuntimeException('The creative image disk cannot produce URLs.');
        }

        $this->disk = $disk;
    }

    /**
     * @return string The public URL of the stored file.
     */
    public function upload(UploadedFileInterface $file): string
    {
        $stream = $file->getStream();
        $stream->rewind();

        $name = Str::random(24).'.'.$this->extensionFor($file->getClientMediaType());

        $this->disk->put($name, $stream->getContents(), 'public');

        $url = $this->disk->url($name);

        if (! is_string($url) || $url === '') {
            throw new RuntimeException('The creative image disk produced no URL.');
        }

        return $url;
    }

    /**
     * Delete a file this uploader stored, if it is one.
     *
     * A URL from anywhere else is left alone: a creative may perfectly well
     * point at an advertiser's own CDN, and deleting that is neither possible
     * nor ours to attempt.
     */
    public function remove(string $url): void
    {
        $name = $this->nameFor($url);

        if ($name !== null && $this->disk->exists($name)) {
            $this->disk->delete($name);
        }
    }

    /**
     * The stored name behind a URL, or null when the URL is not ours.
     */
    public function nameFor(string $url): ?string
    {
        $base = $this->disk->url('');
        $name = Str::after($url, $base);

        // `Str::after` returns the whole string when the needle is absent, so
        // an unrelated URL has to be recognised rather than assumed.
        if ($name === $url || $name === '' || str_contains($name, '/')) {
            return null;
        }

        return $name;
    }

    /**
     * The extension for a media type the validator has already accepted.
     *
     * Chosen from the type rather than kept from the filename, so the name on
     * disk can only ever be one of four things.
     */
    protected function extensionFor(?string $mediaType): string
    {
        return match ($mediaType) {
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            default => 'jpg',
        };
    }
}
