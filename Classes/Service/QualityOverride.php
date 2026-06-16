<?php

declare(strict_types=1);

namespace Plan2net\Webp\Service;

final class QualityOverride
{
    private const QUALITY_TOKEN = '/(\\bquality[\\s=]|\\bQ=)\\d{1,3}(?!\\d)/i';

    private const LOSSLESS_TOKEN = '/\\blossless\\s*=\\s*(?:true|1)\\b/i';

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

    public static function isLossless(string $parameters): bool
    {
        return 1 === \preg_match(self::LOSSLESS_TOKEN, $parameters);
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
