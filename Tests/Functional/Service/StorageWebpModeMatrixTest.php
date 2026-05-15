<?php

declare(strict_types=1);

namespace Plan2net\Webp\Tests\Functional\Service;

use PHPUnit\Framework\Attributes\Test;
use Plan2net\Webp\Service\StorageWebpMode;
use Plan2net\Webp\Tests\Functional\Fixtures\Driver\FakeRemoteDriver;
use TYPO3\CMS\Core\Resource\Driver\DriverRegistry;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class StorageWebpModeMatrixTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = ['install', 'scheduler'];

    protected array $testExtensionsToLoad = ['plan2net/webp'];

    #[Test]
    public function localStorageWithAutoModeIsEnabled(): void
    {
        $storage = $this->createStorage(driver: 'Local', mode: StorageWebpMode::Auto);

        self::assertTrue(StorageWebpMode::isEnabledFor($storage));
    }

    #[Test]
    public function localStorageWithDisabledModeIsOff(): void
    {
        $storage = $this->createStorage(driver: 'Local', mode: StorageWebpMode::Disabled);

        self::assertFalse(StorageWebpMode::isEnabledFor($storage));
    }

    #[Test]
    public function fakeRemoteStorageWithAutoModeIsOff(): void
    {
        $storage = $this->createStorage(driver: 'FakeRemote', mode: StorageWebpMode::Auto);

        self::assertFalse(StorageWebpMode::isEnabledFor($storage));
    }

    #[Test]
    public function fakeRemoteStorageWithEnabledModeIsOn(): void
    {
        $storage = $this->createStorage(driver: 'FakeRemote', mode: StorageWebpMode::Enabled);

        self::assertTrue(StorageWebpMode::isEnabledFor($storage));
    }

    protected function setUp(): void
    {
        parent::setUp();
        GeneralUtility::makeInstance(DriverRegistry::class)->registerDriverClass(
            FakeRemoteDriver::class,
            'FakeRemote',
            'Fake remote driver',
            'FlexForm',
        );
    }

    private function createStorage(string $driver, StorageWebpMode $mode): ResourceStorage
    {
        $basePath = $this->instancePath . '/fileadmin-' . uniqid('webp-');
        mkdir($basePath, 0o775, true);

        $connection = $this->getConnectionPool()->getConnectionForTable('sys_file_storage');
        $connection->insert('sys_file_storage', [
            'name' => 'Test ' . $driver . ' mode ' . $mode->value,
            'driver' => $driver,
            'is_writable' => 1,
            'is_browsable' => 1,
            'is_online' => 1,
            'is_public' => 1,
            'configuration' => sprintf(
                '<T3FlexForms><data><sheet index="sDEF"><language index="lDEF">'
                . '<field index="basePath"><value index="vDEF">%s</value></field>'
                . '<field index="pathType"><value index="vDEF">absolute</value></field>'
                . '</language></sheet></data></T3FlexForms>',
                $basePath,
            ),
            'tx_webp_mode' => $mode->value,
        ]);

        return GeneralUtility::makeInstance(StorageRepository::class)
            ->findByUid((int) $connection->lastInsertId('sys_file_storage'));
    }
}
