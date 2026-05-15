<?php

declare(strict_types=1);

namespace Plan2net\Webp\Service;

use TYPO3\CMS\Core\Resource\ResourceStorage;

enum StorageWebpMode: int
{
    case Auto = 0;
    case Enabled = 1;
    case Disabled = 2;

    public static function isEnabledFor(?ResourceStorage $storage): bool
    {
        if (null === $storage || 0 === $storage->getUid() || !$storage->isWritable()) {
            return false;
        }

        $mode = self::tryFrom((int) ($storage->getStorageRecord()['tx_webp_mode'] ?? self::Auto->value)) ?? self::Auto;

        return match ($mode) {
            self::Enabled => true,
            self::Disabled => false,
            self::Auto => 'Local' === $storage->getDriverType(),
        };
    }
}
