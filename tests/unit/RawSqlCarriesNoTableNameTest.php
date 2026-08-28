<?php

/*
 * This file is part of datlechin/flarum-placements.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\Placements\Tests\unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * A table name inside raw SQL is a table name the query builder never sees,
 * so it never gets the installation's table prefix, and on a prefixed install
 * the column does not exist.
 *
 * This is worth a test of its own because nothing else here can catch it. The
 * suite runs unprefixed by default, so every query looks fine; only four of
 * the seventeen legs of the backend workflow set `DB_PREFIX`, and the failure
 * they produce is a 500 several frames away from the cause. Reading the source
 * for the shape is cheaper and lands on the line.
 */
class RawSqlCarriesNoTableNameTest extends TestCase
{
    /**
     * The methods that take SQL the builder will not touch.
     */
    private const RAW = ['selectRaw', 'whereRaw', 'orderByRaw', 'havingRaw', 'groupByRaw', 'joinRaw', 'raw'];

    /**
     * @return list<string>
     */
    private function sourceFiles(): array
    {
        $files = [];

        /** @var SplFileInfo $file */
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__.'/../../src')) as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    #[Test]
    public function no_raw_expression_names_a_table(): void
    {
        $offenders = [];

        foreach ($this->sourceFiles() as $path) {
            foreach (file($path) ?: [] as $number => $line) {
                // Comments are where this rule gets explained, so they are not
                // where it gets broken.
                $trimmed = ltrim($line);

                if ($trimmed === '' || str_starts_with($trimmed, '//') || str_starts_with($trimmed, '*')) {
                    continue;
                }

                if (! str_contains($line, 'placement_')) {
                    continue;
                }

                foreach (self::RAW as $method) {
                    if (str_contains($line, $method.'(')) {
                        $offenders[] = sprintf(
                            '%s:%d — %s() carries a table name: %s',
                            basename($path),
                            $number + 1,
                            $method,
                            trim($line)
                        );
                    }
                }
            }
        }

        $this->assertSame([], $offenders, implode("\n", [
            'Raw SQL must not name a table: the builder cannot prefix what it does not parse.',
            'Aggregate in a subquery over the one table and join to it, so the raw part needs no qualification.',
            ...$offenders,
        ]));
    }

    /**
     * The check is only worth having if it can see the thing it is looking
     * for, and the shape it looks for is a substring rather than a parse.
     */
    #[Test]
    public function the_check_would_notice_an_offending_line(): void
    {
        $line = "            ->selectRaw('SUM(placement_stats.impressions) as impressions')";

        $this->assertTrue(str_contains($line, 'placement_'));
        $this->assertTrue(str_contains($line, 'selectRaw('));
    }

    #[Test]
    public function it_reads_the_source_it_claims_to_read(): void
    {
        $files = $this->sourceFiles();

        // A path typo would otherwise make the check above pass by finding
        // nothing at all.
        $this->assertGreaterThan(50, count($files));
        $this->assertContains(
            realpath(__DIR__.'/../../src/Http/Controller/AdvertiserReportController.php'),
            array_map('realpath', $files)
        );
    }
}
