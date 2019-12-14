<?php
declare(strict_types=1);

namespace Plan2net\Webp\Converter;

use RuntimeException;

/**
 * Interface Converter
 *
 * @package Plan2net\Webp\Converter
 */
interface Converter
{
    /**
     * @param string $parameters
     */
    public function __construct(string $parameters);

    /**
     * Converts a file $originalFilePath to webp in $targetFilePath.
     *
     * @param string $originalFilePath
     * @param string $targetFilePath
     * @throws RuntimeException
     */
    public function convert(string $originalFilePath, string $targetFilePath);
}
