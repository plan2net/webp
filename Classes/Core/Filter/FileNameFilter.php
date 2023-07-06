<?php

declare(strict_types=1);

namespace Plan2net\Webp\Core\Filter;

use TYPO3\CMS\Core\Resource\Driver\DriverInterface;

final class FileNameFilter
{
    /**
     * Remove generated webp files from file lists,
     * i.e. files that end in .suffix.webp, but not .webp solely.
     */
    public static function filterWebpFiles(
        string $itemName,
        string $itemIdentifier,
        string $parentIdentifier,
        array $additionalInformation,
        DriverInterface $driverInstance
    ): int {
        if (preg_match('/\.[^\.]*\.webp$/', $itemIdentifier) === 1) {
            return -1;
        }

        return 1;
    }
}
