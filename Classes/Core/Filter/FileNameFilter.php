<?php

declare(strict_types=1);

namespace Plan2net\Webp\Core\Filter;

use Plan2net\Webp\Service\Configuration;
use TYPO3\CMS\Core\Resource\Driver\DriverInterface;

final class FileNameFilter
{
    /**
     * Remove generated webp files from file lists,
     * i.e. files ending in .suffix.webp, but not exclusively in .webp.
     */
    public static function filterWebpFiles(
        string $itemName,
        string $itemIdentifier,
        string $parentIdentifier = '',
        array $additionalInformation = [],
        ?DriverInterface $driverInstance = null,
    ): int {
        $pattern = self::getPattern();
        if (null !== $pattern && 1 === \preg_match($pattern, $itemIdentifier)) {
            return -1;
        }

        return 1;
    }

    public static function getPattern(): ?string
    {
        $pattern = (string) Configuration::get('filter_pattern');
        // Test validity
        try {
            if (empty($pattern) || false === \preg_match($pattern, '')) {
                return null;
            }
        } catch (\Throwable) {
            return null;
        }

        return $pattern;
    }
}
