<?php

declare(strict_types=1);

namespace Plan2net\Webp\Tests\Functional\Fixtures\Doubles;

use Plan2net\Webp\Converter\AbstractConverter;
use Plan2net\Webp\Format\OutputFormat;

final class FixtureCopyingConverter extends AbstractConverter
{
    public static string $fixture = '';

    public function convertTo(string $originalFilePath, string $targetFilePath, OutputFormat $format): void
    {
        \copy(self::$fixture, $targetFilePath);
    }
}
