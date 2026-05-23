<?php

declare(strict_types=1);

namespace Plan2net\Webp\Tests\Unit\Format;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Plan2net\Webp\Format\OutputFormat;

final class OutputFormatTest extends TestCase
{
    #[Test]
    #[DataProvider('formatToSuffixProvider')]
    public function suffixIsDotPrefixedExtension(OutputFormat $format, string $expected): void
    {
        self::assertSame($expected, $format->suffix());
    }

    public static function formatToSuffixProvider(): array
    {
        return [
            'webp' => [OutputFormat::Webp, '.webp'],
            'avif' => [OutputFormat::Avif, '.avif'],
            'jxl' => [OutputFormat::Jxl,  '.jxl'],
        ];
    }

    #[Test]
    #[DataProvider('formatToMimeProvider')]
    public function mimeTypeMapsToImageType(OutputFormat $format, string $expected): void
    {
        self::assertSame($expected, $format->mimeType());
    }

    public static function formatToMimeProvider(): array
    {
        return [
            'webp' => [OutputFormat::Webp, 'image/webp'],
            'avif' => [OutputFormat::Avif, 'image/avif'],
            'jxl' => [OutputFormat::Jxl,  'image/jxl'],
        ];
    }

    #[Test]
    public function valueRoundTripsViaTryFrom(): void
    {
        self::assertSame(OutputFormat::Webp, OutputFormat::tryFrom('webp'));
        self::assertSame(OutputFormat::Avif, OutputFormat::tryFrom('avif'));
        self::assertSame(OutputFormat::Jxl, OutputFormat::tryFrom('jxl'));
        self::assertNull(OutputFormat::tryFrom('png'));
    }
}
