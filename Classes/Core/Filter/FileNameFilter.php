<?php

declare(strict_types=1);

namespace Plan2net\Webp\Core\Filter;

use TYPO3\CMS\Core\Resource\Driver\DriverInterface;
use function strlen;
use function strpos;

/**
 * Class FileNameFilter
 *
 * @author Wolfgang Klinger <wk@plan2.net>
 */
class FileNameFilter
{
    /**
     * Remove webp files from file lists
     */
    public static function filterWebpFiles(
        string $itemName,
        string $itemIdentifier,
        string $parentIdentifier,
        array $additionalInformation,
        DriverInterface $driverInstance
    ): int {
        if (strpos($itemIdentifier, '.webp') === strlen($itemIdentifier) - 5) {
            if (false !== strpos($itemIdentifier, '.gif.webp')
                || false !== strpos($itemIdentifier, '.jpg.webp')
                || false !== strpos($itemIdentifier, '.jpeg.webp')
                || false !== strpos($itemIdentifier, '.png.webp')
            ) {
                return -1;
            }
        }

        return 1;
    }
}
