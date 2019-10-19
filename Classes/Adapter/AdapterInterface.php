<?php
declare(strict_types=1);

namespace Plan2net\Webp\Adapter;

/**
 * Interface AdapterInterface
 *
 * @package Plan2net\Webp\Adapter
 */
interface AdapterInterface
{
    /**
     * AdapterInterface constructor.
     * @param string $parameters
     */
    public function __construct(string $parameters);

    /**
     * Convert a file $originalFilePath to webp in $targetFilePath.
     *
     * @param string $originalFilePath
     * @param string $targetFilePath
     * @throws \RuntimeException
     */
    public function convert(string $originalFilePath, string $targetFilePath);
}
