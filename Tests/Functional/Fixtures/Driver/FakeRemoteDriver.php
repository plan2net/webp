<?php

declare(strict_types=1);

namespace Plan2net\Webp\Tests\Functional\Fixtures\Driver;

use TYPO3\CMS\Core\Resource\Driver\LocalDriver;

/**
 * Functional-test driver that behaves like Local on disk but identifies as a
 * separate driver type via the storage record's `driver` column AND simulates
 * a "remote" driver by always returning a temp copy from
 * getFileForLocalProcessing() — that's the remote-driver behavior that makes
 * #108 reproducible.
 *
 * Register at test bootstrap with:
 *   GeneralUtility::makeInstance(DriverRegistry::class)
 *     ->registerDriverClass(FakeRemoteDriver::class, 'FakeRemote', 'Fake remote driver', 'FlexForm');
 *
 * Storage rows in test fixtures must set `driver = 'FakeRemote'` —
 * ResourceStorage::getDriverType() reads from the storage record, not from a
 * method on the driver class.
 */
final class FakeRemoteDriver extends LocalDriver
{
    public function getFileForLocalProcessing($fileIdentifier, $writable = true): string
    {
        return parent::getFileForLocalProcessing($fileIdentifier, true);
    }
}
