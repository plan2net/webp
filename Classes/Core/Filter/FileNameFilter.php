<?php

declare(strict_types=1);

namespace Plan2net\Webp\Core\Filter;

use Plan2net\Webp\Service\Configuration;
use TYPO3\CMS\Core\Resource\Driver\DriverInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class FileNameFilter
{
    /** Hides generated sibling files (.webp/.avif/.jxl) from FAL file lists. */
    public static function filterSiblingFiles(
        string $itemName,
        string $itemIdentifier,
        string $parentIdentifier = '',
        array $additionalInformation = [],
        ?DriverInterface $driverInstance = null,
    ): int {
        $pattern = GeneralUtility::makeInstance(Configuration::class)->getFilterPattern();
        if (null !== $pattern && 1 === \preg_match($pattern, $itemIdentifier)) {
            return -1;
        }

        return 1;
    }
}
