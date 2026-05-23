<?php

declare(strict_types=1);

namespace Plan2net\Webp\Tests\Functional\Fixtures\Doubles;

use Plan2net\Webp\Converter\AbstractConverter;
use Plan2net\Webp\Format\OutputFormat;

/**
 * Test-only Converter that emits a fixed, valid WebP regardless of runtime
 * GD/libwebp version. Used by queue/folder success-path tests to keep them
 * free of environment-sensitive output-size variability. Real-converter
 * coverage stays where it belongs — AfterFileProcessingFunctionalTest.
 */
final class DeterministicWebpConverter extends AbstractConverter
{
    public function convertTo(string $originalFilePath, string $targetFilePath, OutputFormat $format): void
    {
        \copy(__DIR__ . '/../Images/tiny.webp', $targetFilePath);
    }
}
