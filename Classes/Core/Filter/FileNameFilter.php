<?php
declare(strict_types=1);

namespace Plan2net\Webp\Core\Filter;

use TYPO3\CMS\Core\Resource\Driver\DriverInterface;

/**
 * Class FileNameFilter
 *
 * @package Plan2net\Webp\Core\Filter
 * @author Wolfgang Klinger <wk@plan2.net>
 */
class FileNameFilter
{
    /**
     * Remove webp files from file lists
     *
     * @param string $itemName
     * @param string $itemIdentifier
     * @param string $parentIdentifier
     * @param array $additionalInformation
     * @param DriverInterface $driverInstance
     * @return bool|int
     */
    public static function filterWebpFiles(
        string $itemName,
        string $itemIdentifier,
        string $parentIdentifier,
        array $additionalInformation,
        DriverInterface $driverInstance
    ) {
        if (strpos($itemIdentifier, '.webp') === strlen($itemIdentifier) - 5) {
            return -1;
        }

        return true;
    }
}