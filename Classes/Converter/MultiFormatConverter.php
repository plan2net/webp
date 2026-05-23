<?php

declare(strict_types=1);

namespace Plan2net\Webp\Converter;

use Plan2net\Webp\Converter\Exception\UnsupportedFormatException;
use Plan2net\Webp\Format\OutputFormat;

interface MultiFormatConverter
{
    /**
     * @throws \RuntimeException
     * @throws UnsupportedFormatException
     */
    public function convertTo(string $originalFilePath, string $targetFilePath, OutputFormat $format): void;
}
