<?php

declare(strict_types=1);

namespace Plan2net\Webp\Tests\Functional\EventListener;

use PHPUnit\Framework\Attributes\Test;
use Plan2net\Webp\Converter\PhpGdConverter;
use Plan2net\Webp\Tests\Functional\Fixtures\Driver\FakeRemoteDriver;
use TYPO3\CMS\Core\Resource\Driver\DriverRegistry;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class AfterFileProcessingRemoteDriverTest extends FunctionalTestCase
{
    private const FIXTURE_PNG = __DIR__ . '/../Fixtures/Images/tiny.png';

    protected array $coreExtensionsToLoad = ['install', 'scheduler'];

    protected array $testExtensionsToLoad = ['plan2net/webp'];

    #[Test]
    public function siblingIsWrittenOnFakeRemoteStorageWhenModeIsEnabled(): void
    {
        [$storage, $basePath] = $this->createFakeRemoteStorage(mode: 1);
        \copy(self::FIXTURE_PNG, $basePath . '/sample.png');

        $file = $storage->getFile('sample.png');
        self::assertInstanceOf(File::class, $file);
        $processedFile = $file->process(
            ProcessedFile::CONTEXT_IMAGECROPSCALEMASK,
            ['width' => 16, 'height' => 16],
        );

        // FakeRemoteDriver::getFileForLocalProcessing() returns a temp copy
        // (simulating a real remote driver), so we can't compose the .webp
        // path from there. The disk path on the storage is basePath +
        // storage-relative identifier.
        self::assertFileExists($basePath . $processedFile->getIdentifier() . '.webp');
    }

    #[Test]
    public function siblingIsNotWrittenOnFakeRemoteStorageWithAutoMode(): void
    {
        [$storage, $basePath] = $this->createFakeRemoteStorage(mode: 0);
        \copy(self::FIXTURE_PNG, $basePath . '/sample.png');

        $file = $storage->getFile('sample.png');
        self::assertInstanceOf(File::class, $file);
        $processedFile = $file->process(
            ProcessedFile::CONTEXT_IMAGECROPSCALEMASK,
            ['width' => 16, 'height' => 16],
        );

        self::assertFileDoesNotExist($basePath . $processedFile->getIdentifier() . '.webp');
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
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['webp'] = [
            'converter' => PhpGdConverter::class,
            'parameters' => 'image/jpeg::-quality 85|image/png::-quality 75|image/gif::-quality 85',
            'mime_types' => 'image/jpeg,image/png,image/gif',
            'convert_all' => '1',
            'exclude_directories' => '',
            'silent' => '0',
            'use_system_settings' => '0',
            'hide_webp' => '1',
            'filter_pattern' => '/\\.(jpe?g|png|gif)\\.webp$/i',
            'async' => '0',
            'async_throttle_ms' => '0',
        ];
    }

    /**
     * @return array{0: ResourceStorage, 1: string} [storage, basePath]
     */
    private function createFakeRemoteStorage(int $mode): array
    {
        $basePath = $this->instancePath . '/fileadmin-remote-' . uniqid();
        \mkdir($basePath, 0o775, true);

        $connection = $this->getConnectionPool()->getConnectionForTable('sys_file_storage');
        $row = [
            'name' => 'Fake remote mode ' . $mode,
            'driver' => 'FakeRemote',
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
            'tx_webp_mode' => $mode,
        ];
        $connection->insert('sys_file_storage', $row);
        $uid = (int) $connection->lastInsertId();

        // Bypass StorageRepository's local cache by passing the row directly.
        $row['uid'] = $uid;
        $storage = GeneralUtility::makeInstance(StorageRepository::class)
            ->getStorageObject($uid, $row);

        return [$storage, $basePath];
    }
}
