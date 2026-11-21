<?php

declare(strict_types=1);

namespace Plan2net\Webp\Tests\Functional\Fixtures\Doubles;

use Plan2net\Webp\Converter\AbstractConverter;
use Plan2net\Webp\Format\OutputFormat;

/**
 * Like ImageMagick without an encoder for the target format: succeeds, but writes JPEG.
 */
final class JpegOutputConverter extends AbstractConverter
{
    public const BYTES = "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00\xFF\xD9";

    public function convertTo(string $originalFilePath, string $targetFilePath, OutputFormat $format): void
    {
        \file_put_contents($targetFilePath, self::BYTES);
    }
}
