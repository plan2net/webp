<?php

declare(strict_types=1);

namespace Plan2net\Webp\Converter;

use Plan2net\Webp\Format\OutputFormat;
use Plan2net\Webp\Service\Configuration;

abstract class AbstractConverter implements Converter, MultiFormatConverter
{
    public function __construct(
        protected readonly string $parameters,
        protected readonly Configuration $configuration,
    ) {
    }

    abstract public function convertTo(string $originalFilePath, string $targetFilePath, OutputFormat $format): void;

    public function convert(string $originalFilePath, string $targetFilePath): void
    {
        $this->convertTo($originalFilePath, $targetFilePath, OutputFormat::Webp);
    }
}
