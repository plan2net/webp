<?php

declare(strict_types=1);

namespace Plan2net\Webp\Service;

final class QualityOverride
{
    private const QUALITY_TOKEN = '/(\\bquality[\\s=]|\\bQ=)\\d{1,3}(?!\\d)/i';

    public static function fromMetadataValue(mixed $value): ?int
    {
        if (!\is_numeric($value)) {
            return null;
        }

        $quality = (int) $value;
        if ($quality < 1 || $quality > 100) {
            return null;
        }

        return $quality;
    }

    public static function applyToParameters(string $parameters, int $quality): string
    {
        return \preg_replace_callback(
            self::QUALITY_TOKEN,
            static fn (array $matches): string => $matches[1] . $quality,
            $parameters,
            1,
        ) ?? $parameters;
    }
}
