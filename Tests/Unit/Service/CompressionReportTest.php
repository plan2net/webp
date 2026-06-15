<?php

declare(strict_types=1);

namespace Plan2net\Webp\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Plan2net\Webp\Service\CompressionReport;

final class CompressionReportTest extends TestCase
{
    #[Test]
    public function emptyInputProducesEmptyReport(): void
    {
        self::assertSame([], CompressionReport::build([], 0, ''));
    }

    #[Test]
    public function pairsSiblingToItsBaseVariantAndComputesSavings(): void
    {
        $rows = [
            ['identifier' => '/_p/csm_a.jpg', 'size' => 1000, 'width' => 64, 'height' => 36, 'format' => null],
            ['identifier' => '/_p/csm_a.jpg.webp', 'size' => 400, 'width' => 64, 'height' => 36, 'format' => 'webp'],
        ];

        $report = CompressionReport::build($rows, 99999, '/orig.jpg');

        self::assertCount(1, $report);
        self::assertSame('64×36', $report[0]['label']);
        self::assertSame(1000, $report[0]['sourceSize']);
        self::assertSame(400, $report[0]['results']['webp']['size']);
        self::assertSame(60, $report[0]['results']['webp']['savingsPercent']);
    }

    #[Test]
    public function multipleFormatsForOneVariantAreGrouped(): void
    {
        $rows = [
            ['identifier' => '/_p/csm_a.jpg', 'size' => 1000, 'width' => 64, 'height' => 36, 'format' => null],
            ['identifier' => '/_p/csm_a.jpg.webp', 'size' => 400, 'width' => 64, 'height' => 36, 'format' => 'webp'],
            ['identifier' => '/_p/csm_a.jpg.avif', 'size' => 300, 'width' => 64, 'height' => 36, 'format' => 'avif'],
        ];

        $report = CompressionReport::build($rows, 99999, '/orig.jpg');

        self::assertArrayHasKey('webp', $report[0]['results']);
        self::assertArrayHasKey('avif', $report[0]['results']);
        self::assertSame(70, $report[0]['results']['avif']['savingsPercent']);
    }

    #[Test]
    public function siblingNextToOriginalPairsAgainstOriginalSize(): void
    {
        $rows = [
            ['identifier' => '/orig.jpg.webp', 'size' => 500, 'width' => 800, 'height' => 600, 'format' => 'webp'],
        ];

        $report = CompressionReport::build($rows, 1000, '/orig.jpg');

        self::assertCount(1, $report);
        self::assertSame(1000, $report[0]['sourceSize']);
        self::assertSame(50, $report[0]['results']['webp']['savingsPercent']);
    }

    #[Test]
    public function orphanSiblingWithoutBaseIsSkipped(): void
    {
        $rows = [
            ['identifier' => '/_p/csm_gone.jpg.webp', 'size' => 400, 'width' => 64, 'height' => 36, 'format' => 'webp'],
        ];

        self::assertSame([], CompressionReport::build($rows, 1000, '/orig.jpg'));
    }

    #[Test]
    public function baseVariantWithoutSiblingsIsNotListed(): void
    {
        $rows = [
            ['identifier' => '/_p/csm_a.jpg', 'size' => 1000, 'width' => 64, 'height' => 36, 'format' => null],
        ];

        self::assertSame([], CompressionReport::build($rows, 1000, '/orig.jpg'));
    }

    #[Test]
    public function variantsAreSortedBySourceSizeDescending(): void
    {
        $rows = [
            ['identifier' => '/_p/small.jpg', 'size' => 100, 'width' => 16, 'height' => 16, 'format' => null],
            ['identifier' => '/_p/small.jpg.webp', 'size' => 50, 'width' => 16, 'height' => 16, 'format' => 'webp'],
            ['identifier' => '/_p/big.jpg', 'size' => 9000, 'width' => 256, 'height' => 256, 'format' => null],
            ['identifier' => '/_p/big.jpg.webp', 'size' => 3000, 'width' => 256, 'height' => 256, 'format' => 'webp'],
        ];

        $report = CompressionReport::build($rows, 1, '/orig.jpg');

        self::assertSame(9000, $report[0]['sourceSize']);
        self::assertSame(100, $report[1]['sourceSize']);
    }

    #[Test]
    public function savingsPercentRoundsHalfAwayFromZero(): void
    {
        $rows = [
            ['identifier' => '/_p/csm_a.jpg', 'size' => 1000, 'width' => 64, 'height' => 36, 'format' => null],
            ['identifier' => '/_p/csm_a.jpg.webp', 'size' => 555, 'width' => 64, 'height' => 36, 'format' => 'webp'],
        ];

        // (1000 - 555) / 1000 = 44.5% rounds half away from zero to 45
        $report = CompressionReport::build($rows, 99999, '/orig.jpg');

        self::assertSame(45, $report[0]['results']['webp']['savingsPercent']);
    }

    #[Test]
    public function savingsPercentIsNegativeWhenSiblingLargerThanSource(): void
    {
        $rows = [
            ['identifier' => '/_p/csm_a.jpg', 'size' => 1000, 'width' => 64, 'height' => 36, 'format' => null],
            ['identifier' => '/_p/csm_a.jpg.webp', 'size' => 1200, 'width' => 64, 'height' => 36, 'format' => 'webp'],
        ];

        // (1000 - 1200) / 1000 = -20%
        $report = CompressionReport::build($rows, 99999, '/orig.jpg');

        self::assertSame(-20, $report[0]['results']['webp']['savingsPercent']);
    }
}
