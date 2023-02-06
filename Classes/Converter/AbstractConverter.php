<?php

declare(strict_types=1);

namespace Plan2net\Webp\Converter;

abstract class AbstractConverter implements Converter
{
    public function __construct(protected readonly string $parameters)
    {
    }

    abstract public function convert(string $originalFilePath, string $targetFilePath): void;
}
