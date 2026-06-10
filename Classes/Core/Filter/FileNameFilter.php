<?php

declare(strict_types=1);

namespace Plan2net\Webp\Core\Filter;

use Plan2net\Webp\Service\Configuration;
use TYPO3\CMS\Core\Resource\Driver\DriverInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class FileNameFilter
{
    private static ?string $pattern = null;
    private static bool $patternResolved = false;

    /** Hides generated sibling files (.webp/.avif/.jxl) from FAL file lists. */
    public static function filterSiblingFiles(
        string $itemName,
        string $itemIdentifier,
        string $parentIdentifier = '',
        array $additionalInformation = [],
        ?DriverInterface $driverInstance = null,
    ): int {
        if (!self::$patternResolved) {
            self::$pattern = GeneralUtility::makeInstance(Configuration::class)->getFilterPattern();
            self::$patternResolved = true;
        }

        if (null !== self::$pattern && 1 === \preg_match(self::$pattern, $itemIdentifier)) {
            return -1;
        }

        return 1;
    }
}
