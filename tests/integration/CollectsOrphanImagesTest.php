<?php

/*
 * This file is part of datlechin/flarum-placements.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\Placements\Tests\integration;

use Datlechin\Placements\Upload\OrphanCollector;
use Flarum\Foundation\Paths;
use Flarum\Testing\integration\ConsoleTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Uploaded images nothing points at any more.
 *
 * `CreativeTypeInterface::assets()` was documented as the hook for this,
 * implemented by every type, and called from nowhere -- so every replaced
 * banner and every deleted creative left its file behind for good.
 *
 * The interesting cases are the ones it must NOT delete: a file somebody
 * uploaded minutes ago and has not finished a form for, and an address on an
 * advertiser's own CDN that is not ours to reason about in the first place.
 */
class CollectsOrphanImagesTest extends ConsoleTestCase
{
    use SeedsCampaigns;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('datlechin-placements');
        $this->prepareDatabase(['group_permission' => [], ...$this->campaignSeed()]);

        $this->dir = $this->app()->getContainer()->make(Paths::class)->public.'/assets/placements';

        if (! is_dir($this->dir)) {
            mkdir($this->dir, 0755, true);
        }
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir.'/*') ?: [] as $file) {
            unlink($file);
        }

        parent::tearDown();
    }

    /**
     * @param  int  $ageHours  How long ago the file was last written.
     */
    private function file(string $name, int $ageHours): string
    {
        $path = $this->dir.'/'.$name;

        file_put_contents($path, 'not really an image, and nothing here reads one');
        touch($path, time() - $ageHours * 3600);

        return $path;
    }

    private function pointCreativeAt(string $url): void
    {
        $this->database()->table('placement_creatives')->where('id', 1)->update([
            'payload' => json_encode(['asset' => $url]),
        ]);
    }

    private function collect(bool $dryRun = false): array
    {
        return $this->app()->getContainer()->make(OrphanCollector::class)->collect($dryRun);
    }

    /**
     * The URL the uploader would have produced for a stored name.
     */
    private function storedUrl(string $name): string
    {
        return $this->app()->getContainer()->make('flarum.config')['url'].'/assets/placements/'.$name;
    }

    #[Test]
    public function a_file_nothing_refers_to_is_collected(): void
    {
        $path = $this->file('orphan.png', OrphanCollector::GRACE_HOURS + 1);

        $this->assertSame(['orphan.png'], $this->collect());
        $this->assertFileDoesNotExist($path);
    }

    #[Test]
    public function a_file_a_creative_still_refers_to_is_kept(): void
    {
        $path = $this->file('inuse.png', OrphanCollector::GRACE_HOURS + 1);
        $this->pointCreativeAt($this->storedUrl('inuse.png'));

        $this->assertSame([], $this->collect());
        $this->assertFileExists($path);
    }

    /**
     * The race this grace period exists for: somebody uploads an image and
     * then spends ten minutes writing the rest of the form.
     */
    #[Test]
    public function a_file_uploaded_a_moment_ago_is_left_alone(): void
    {
        $path = $this->file('justnow.png', 1);

        $this->assertSame([], $this->collect());
        $this->assertFileExists($path);
    }

    /**
     * A logo wall holds a list of them, so looking for a field called `asset`
     * would collect every logo on the forum.
     */
    #[Test]
    public function every_asset_a_type_declares_is_counted(): void
    {
        $this->file('logo-a.png', OrphanCollector::GRACE_HOURS + 1);
        $this->file('logo-b.png', OrphanCollector::GRACE_HOURS + 1);

        $this->database()->table('placement_creatives')->where('id', 1)->update([
            'type' => 'logo_wall',
            'payload' => json_encode([
                'logos' => [
                    ['asset' => $this->storedUrl('logo-a.png')],
                    ['asset' => $this->storedUrl('logo-b.png')],
                ],
                'columns' => 2,
            ]),
        ]);

        $this->assertSame([], $this->collect());
    }

    /**
     * A creative may point at an advertiser's own CDN, which is neither ours
     * to delete nor ours to keep.
     */
    #[Test]
    public function an_address_somewhere_else_is_ignored_entirely(): void
    {
        $this->pointCreativeAt('https://cdn.acme.example/banner.png');

        $orphan = $this->file('orphan.png', OrphanCollector::GRACE_HOURS + 1);

        // The foreign URL neither protects the local file nor is treated as
        // one to delete.
        $this->assertSame(['orphan.png'], $this->collect());
        $this->assertFileDoesNotExist($orphan);
    }

    #[Test]
    public function a_dry_run_reports_without_deleting(): void
    {
        $path = $this->file('orphan.png', OrphanCollector::GRACE_HOURS + 1);

        $this->assertSame(['orphan.png'], $this->collect(true));
        $this->assertFileExists($path);
    }

    #[Test]
    public function the_prune_command_collects_them_too(): void
    {
        $path = $this->file('orphan.png', OrphanCollector::GRACE_HOURS + 1);

        $output = $this->runCommand(['command' => 'placements:prune']);

        $this->assertStringContainsString('1 uploaded image', $output);
        $this->assertFileDoesNotExist($path);
    }

    #[Test]
    public function the_prune_command_can_be_told_to_leave_them(): void
    {
        $path = $this->file('orphan.png', OrphanCollector::GRACE_HOURS + 1);

        $this->runCommand(['command' => 'placements:prune', '--keep-images' => true]);

        $this->assertFileExists($path);
    }

    #[Test]
    public function a_dry_run_of_the_command_deletes_neither_buckets_nor_images(): void
    {
        $path = $this->file('orphan.png', OrphanCollector::GRACE_HOURS + 1);

        $output = $this->runCommand(['command' => 'placements:prune', '--dry-run' => true]);

        $this->assertStringContainsString('Would delete', $output);
        $this->assertFileExists($path);
    }
}
