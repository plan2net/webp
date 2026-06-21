<?php

declare(strict_types=1);

namespace Plan2net\Webp\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Plan2net\Webp\Format\OutputFormat;
use Plan2net\Webp\Service\QualityOverride;
use TYPO3\CMS\Core\Resource\File;

final class QualityOverrideTest extends TestCase
{
    #[Test]
    #[DataProvider('forcedQualityProvider')]
    public function forcedQualityForDerivesTheQualityFromModeAndQuality(mixed $mode, mixed $quality, ?int $expected): void
    {
        self::assertSame($expected, QualityOverride::forcedQualityFor($this->fileWithMetadata($mode, $quality)));
    }

    public static function forcedQualityProvider(): array
    {
        return [
            'force mode with quality' => ['force', 50, 50],
            'force mode without quality' => ['force', 0, null],
            'force mode with out-of-range quality' => ['force', 101, null],
            'legacy row with quality forces it' => ['', 40, 40],
            'legacy row without quality' => ['', 0, null],
            'global mode ignores a stale quality' => ['global', 60, null],
            'unknown mode is treated as global' => ['auto', 60, null],
            'mode with surrounding whitespace' => [' force ', 50, 50],
            'null mode from un-migrated schema' => [null, 40, 40],
        ];
    }

    #[Test]
    public function formatConfigurationStripsAnInboundQualityAndAppendsTheForcedOne(): void
    {
        $configuration = QualityOverride::formatConfiguration(
            ['width' => 200, 'tx_webp_quality' => 85],
            OutputFormat::Webp,
            50,
        );

        self::assertSame(['width' => 200, 'format' => 'webp', 'webp' => true, 'tx_webp_quality' => 50], $configuration);
    }

    #[Test]
    public function formatConfigurationOmitsTheQualityKeyWithoutAForcedQuality(): void
    {
        $configuration = QualityOverride::formatConfiguration(
            ['width' => 200, 'tx_webp_quality' => 85],
            OutputFormat::Avif,
            null,
        );

        self::assertSame(['width' => 200, 'format' => 'avif', 'webp' => true], $configuration);
    }

    private function fileWithMetadata(mixed $mode, mixed $quality): File
    {
        $file = $this->createMock(File::class);
        $file->method('getProperty')->willReturnMap([
            ['tx_webp_quality_mode', $mode],
            ['tx_webp_quality', $quality],
        ]);

        return $file;
    }

    #[Test]
    #[DataProvider('metadataValueProvider')]
    public function fromMetadataValueValidatesAndCoerces(mixed $value, ?int $expected): void
    {
        self::assertSame($expected, QualityOverride::fromMetadataValue($value));
    }

    public static function metadataValueProvider(): array
    {
        return [
            'numeric string in range' => ['50', 50],
            'numeric string upper bound' => ['100', 100],
            'numeric string lower bound' => ['1', 1],
            'integer in range' => [50, 50],
            'integer upper bound' => [100, 100],
            'zero string means unset' => ['0', null],
            'zero int means unset' => [0, null],
            'empty string' => ['', null],
            'null' => [null, null],
            'above range string' => ['101', null],
            'above range int' => [101, null],
            'negative string' => ['-5', null],
            'non-numeric' => ['abc', null],
        ];
    }

    #[Test]
    #[DataProvider('parameterProvider')]
    public function applyToParametersSubstitutesTheQualityToken(string $parameters, int $quality, string $expected): void
    {
        self::assertSame($expected, QualityOverride::applyToParameters($parameters, $quality));
    }

    public static function parameterProvider(): array
    {
        return [
            'magick -quality' => ['-quality 85 -define webp:lossless=false', 50, '-quality 50 -define webp:lossless=false'],
            'gd quality=' => ['quality=85', 50, 'quality=50'],
            'vips Q=' => ['Q=80', 50, 'Q=50'],
            'lossless string has no quality token' => ['-define webp:lossless=true', 50, '-define webp:lossless=true'],
            'malformed out-of-range token is left untouched' => ['quality=1000', 50, 'quality=1000'],
            'lower boundary' => ['-quality 85', 1, '-quality 1'],
            'upper boundary' => ['-quality 85', 100, '-quality 100'],
        ];
    }

    #[Test]
    #[DataProvider('losslessProvider')]
    public function isLosslessDetectsLosslessParameterStrings(string $parameters, bool $expected): void
    {
        self::assertSame($expected, QualityOverride::isLossless($parameters));
    }

    public static function losslessProvider(): array
    {
        return [
            'magick webp lossless' => ['-quality 75 -define webp:lossless=true', true],
            'vips lossless' => ['Q=75 lossless=true effort=4', true],
            'lossless one' => ['lossless=1', true],
            'magick lossless false' => ['-quality 85 -define webp:lossless=false', false],
            'lossless zero' => ['lossless=0', false],
            'lossless_jpeg is not lossless flag' => ['/usr/bin/cjxl --lossless_jpeg=0 %s %s', false],
            'plain lossy quality' => ['-quality 85', false],
            'vips Q only' => ['Q=80', false],
        ];
    }
}
