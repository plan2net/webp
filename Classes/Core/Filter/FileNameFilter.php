<?php

declare(strict_types=1);

namespace Plan2net\Webp\Core\Filter;

use TYPO3\CMS\Core\Resource\Driver\DriverInterface;

final class FileNameFilter
{
    /**
     * Remove webp files from file lists.
     */
    public static function filterWebpFiles(
        string $itemName,
        string $itemIdentifier,
        string $parentIdentifier,
        array $additionalInformation,
        DriverInterface $driverInstance
    ): int {
        if (str_ends_with($itemIdentifier, '.webp')) {
            return -1;
        }

        return 1;
    }
}
