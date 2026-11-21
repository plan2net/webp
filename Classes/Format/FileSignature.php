<?php

declare(strict_types=1);

namespace Plan2net\Webp\Format;

final class FileSignature
{
    private const HEADER_LENGTH = 1024;

    public static function read(string $path): string
    {
        return (string) @\file_get_contents($path, false, null, 0, self::HEADER_LENGTH);
    }

    public static function matches(OutputFormat $format, string $header): bool
    {
        return match ($format) {
            OutputFormat::Webp => \str_starts_with($header, 'RIFF') && 'WEBP' === \substr($header, 8, 4),
            OutputFormat::Avif => [] !== \array_intersect(['avif', 'avis'], self::isoBrands($header)),
            OutputFormat::Jxl => \str_starts_with($header, "\xFF\x0A")
                || \str_starts_with($header, "\x00\x00\x00\x0CJXL \x0D\x0A\x87\x0A"),
        };
    }

    public static function describe(string $header): ?string
    {
        foreach (OutputFormat::cases() as $format) {
            if (self::matches($format, $header)) {
                return $format->label();
            }
        }

        return match (true) {
            \str_starts_with($header, "\xFF\xD8\xFF") => 'JPEG',
            \str_starts_with($header, "\x89PNG") => 'PNG',
            \str_starts_with($header, 'GIF8') => 'GIF',
            default => null,
        };
    }

    /**
     * Some encoders write mif1 as the major brand and list avif only among the compatible brands.
     *
     * @return list<string>
     */
    private static function isoBrands(string $header): array
    {
        if ('ftyp' !== \substr($header, 4, 4)) {
            return [];
        }
        $boxSize = \min((int) \unpack('N', $header)[1], \strlen($header));
        $brands = [\substr($header, 8, 4)];
        for ($offset = 16; $offset + 4 <= $boxSize; $offset += 4) {
            $brands[] = \substr($header, $offset, 4);
        }

        return $brands;
    }
}
