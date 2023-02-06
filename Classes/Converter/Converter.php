<?php

declare(strict_types=1);

namespace Plan2net\Webp\Converter;

interface Converter
{
    public function __construct(string $parameters);

    /**
     * Converts a file $originalFilePath to webp in $targetFilePath.
     *
     * @throws \RuntimeException
     */
    public function convert(string $originalFilePath, string $targetFilePath): void;
}
