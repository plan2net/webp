<?php

declare(strict_types=1);

namespace Plan2net\Webp\Tests\Functional\Fixtures\Doubles;

use Plan2net\Webp\Converter\AbstractConverter;
use Plan2net\Webp\Format\OutputFormat;

final class SmallVariantGrowsConverter extends AbstractConverter
{
    public function convertTo(string $originalFilePath, string $targetFilePath, OutputFormat $format): void
    {
        $width = (int) (\getimagesize($originalFilePath)[0] ?? 0);
        if ($width < 32) {
            \file_put_contents($targetFilePath, \str_repeat('x', (int) \filesize($originalFilePath) + 1));

            return;
        }
        \copy(__DIR__ . '/../Images/tiny.webp', $targetFilePath);
    }
}
