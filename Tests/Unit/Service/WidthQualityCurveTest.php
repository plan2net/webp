<?php

declare(strict_types=1);

namespace Plan2net\Webp\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Plan2net\Webp\Service\WidthQualityCurve;

final class WidthQualityCurveTest extends TestCase
{
    #[Test]
    public function parseReturnsBandsSortedAscendingByWidth(): void
    {
        self::assertSame(
            [
                ['maxWidth' => 640, 'quality' => 80],
                ['maxWidth' => 1024, 'quality' => 72],
                ['maxWidth' => 1536, 'quality' => 64],
            ],
            WidthQualityCurve::parse('1536:64|640:80|1024:72'),
        );
    }

    #[Test]
    #[DataProvider('malformedProvider')]
    public function parseDropsInvalidEntries(string $curve, array $expected): void
    {
        self::assertSame($expected, WidthQualityCurve::parse($curve));
    }

    public static function malformedProvider(): array
    {
        return [
            'empty string' => ['', []],
            'quality zero dropped' => ['640:0', []],
            'quality above 100 dropped' => ['640:101', []],
            'negative width dropped' => ['-640:80', []],
            'non-numeric dropped' => ['abc:80|640:def', []],
            'missing colon dropped' => ['640', []],
            'partially malformed keeps valid band' => ['640:80|junk|1536:0|1024:72', [
                ['maxWidth' => 640, 'quality' => 80],
                ['maxWidth' => 1024, 'quality' => 72],
            ]],
        ];
    }

    #[Test]
    #[DataProvider('widthProvider')]
    public function qualityForWidthSelectsTheBand(int $width, ?int $expected): void
    {
        $bands = WidthQualityCurve::parse('640:80|1024:72|1536:64');

        self::assertSame($expected, WidthQualityCurve::qualityForWidth($bands, $width));
    }

    public static function widthProvider(): array
    {
        return [
            'below smallest' => [320, 80],
            'exact smallest' => [640, 80],
            'between bands' => [800, 72],
            'exact largest' => [1536, 64],
            'above largest uses largest' => [4096, 64],
        ];
    }

    #[Test]
    public function qualityForWidthReturnsNullForEmptyBands(): void
    {
        self::assertNull(WidthQualityCurve::qualityForWidth([], 1536));
    }

    #[Test]
    public function singleBandCoversAllWidths(): void
    {
        $bands = WidthQualityCurve::parse('99999:55');

        self::assertSame(55, WidthQualityCurve::qualityForWidth($bands, 16));
        self::assertSame(55, WidthQualityCurve::qualityForWidth($bands, 5000));
    }
}
