<?php

declare(strict_types=1);

namespace Plan2net\Webp\Service;

use Plan2net\Webp\Format\OutputFormat;
use TYPO3\CMS\Core\Resource\File;

final class QualityOverride
{
    private const QUALITY_TOKEN = '/(\\bquality[\\s=]|\\bQ=)\\d{1,3}(?!\\d)/i';

    private const LOSSLESS_TOKEN = '/\\blossless\\s*=\\s*(?:true|1)\\b/i';

    public static function forcedQualityFor(File $file): ?int
    {
        $mode = \strtolower(\trim((string) $file->getProperty('tx_webp_quality_mode')));
        if ('' !== $mode && 'force' !== $mode) {
            return null;
        }

        return self::fromMetadataValue($file->getProperty('tx_webp_quality'));
    }

    // The enqueue side (AfterFileProcessing) and the convert side (SiblingGenerator) must derive
    // identical configuration hashes or the same conversion re-enqueues forever; the width curve
    // must never enter the configuration.
    public static function formatConfiguration(array $taskConfiguration, OutputFormat $format, ?int $forcedQuality): array
    {
        unset($taskConfiguration['tx_webp_quality']);
        $configuration = $taskConfiguration + ['format' => $format->value, 'webp' => true];
        if (null !== $forcedQuality) {
            $configuration['tx_webp_quality'] = $forcedQuality;
        }

        return $configuration;
    }

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
