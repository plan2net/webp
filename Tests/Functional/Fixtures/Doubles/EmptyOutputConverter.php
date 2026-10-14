<?php

declare(strict_types=1);

namespace Plan2net\Webp\Tests\Functional\Fixtures\Doubles;

use Plan2net\Webp\Converter\AbstractConverter;
use Plan2net\Webp\Format\OutputFormat;

/**
 * Mimics an ImageMagick/GraphicsMagick run killed by a process or memory limit.
 */
final class EmptyOutputConverter extends AbstractConverter
{
    public function convertTo(string $originalFilePath, string $targetFilePath, OutputFormat $format): void
    {
        \file_put_contents($targetFilePath, '');
    }
}
