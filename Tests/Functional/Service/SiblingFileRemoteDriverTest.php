<?php

declare(strict_types=1);

namespace Plan2net\Webp\Tests\Functional\Service;

use PHPUnit\Framework\Attributes\Test;
use Plan2net\Webp\Tests\Functional\Fixtures\Driver\FakeRemoteDriver;
use TYPO3\CMS\Core\Resource\Driver\DriverRegistry;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class SiblingFileRemoteDriverTest extends FunctionalTestCase
{
    private const FIXTURE_PNG = __DIR__ . '/../../Functional/Fixtures/Images/tiny.png';

    protected array $coreExtensionsToLoad = ['install', 'scheduler'];

    protected array $testExtensionsToLoad = ['plan2net/webp'];

    private ResourceStorage $storage;
    private string $storageBasePath;

    #[Test]
    public function siblingIsDeletedWhenSourceFileIsReplacedOnRemoteDriver(): void
    {
        $original = $this->placeImage('photo.png');
        $this->placeBytes('photo.png.webp', 'webp-bytes');
        self::assertFileExists($this->storageBasePath . '/photo.png.webp');

        $replacement = $this->instancePath . '/replacement.png';
        \copy(self::FIXTURE_PNG, $replacement);
        $this->storage->replaceFile($original, $replacement);

        self::assertFileDoesNotExist($this->storageBasePath . '/photo.png.webp');
    }

    #[Test]
    public function siblingIsDeletedWhenSourceFileIsDeletedOnRemoteDriver(): void
    {
        $original = $this->placeImage('photo.png');
        $this->placeBytes('photo.png.webp', 'webp-bytes');

        $original->delete();

        self::assertFileDoesNotExist($this->storageBasePath . '/photo.png.webp');
    }

    #[Test]
    public function siblingFollowsRenamedSourceFileOnRemoteDriver(): void
    {
        $original = $this->placeImage('photo.png');
        $this->placeBytes('photo.png.webp', 'webp-bytes');

        $this->storage->renameFile($original, 'renamed.png');

        self::assertFileDoesNotExist($this->storageBasePath . '/photo.png.webp');
        self::assertFileExists($this->storageBasePath . '/renamed.png.webp');
    }

    #[Test]
    public function siblingMovesAlongsideSourceFileWithinRemoteStorage(): void
    {
        \mkdir($this->storageBasePath . '/dst', 0o775, true);
        $original = $this->placeImage('moveme.png');
        $this->placeBytes('moveme.png.webp', 'webp-bytes');

        $targetFolder = $this->storage->getFolder('/dst/');
        $this->storage->moveFile($original, $targetFolder);

        self::assertFileDoesNotExist($this->storageBasePath . '/moveme.png.webp');
        self::assertFileExists($this->storageBasePath . '/dst/moveme.png.webp');
    }

    #[Test]
    public function siblingIsCleanedUpFromSourceOnCrossStorageMove(): void
    {
        // Use the default Local fileadmin storage (UID 1) as the target.
        $localStorage = GeneralUtility::makeInstance(StorageRepository::class)->findByUid(1);
        $localBase = $this->instancePath . '/fileadmin';
        if (!\is_dir($localBase)) {
            \mkdir($localBase, 0o775, true);
        }

        $original = $this->placeImage('crossme.png');
        $this->placeBytes('crossme.png.webp', 'webp-bytes');

        $targetFolder = $localStorage->getRootLevelFolder();
        $this->storage->moveFile($original, $targetFolder);

        self::assertFileDoesNotExist($this->storageBasePath . '/crossme.png.webp');
        self::assertFileDoesNotExist($localBase . '/crossme.png.webp');
    }

    #[Test]
    public function manuallyUploadedWebpFileIsNotTreatedAsSibling(): void
    {
        $webp = $this->placeBytes('orphan.webp', 'webp-bytes');
        $webp->delete();

        // FAL deletes the file itself; we just verify we didn't touch a
        // non-existent orphan.webp.webp.
        self::assertFileDoesNotExist($this->storageBasePath . '/orphan.webp.webp');
    }

    #[Test]
    public function autoModeRemoteStorageLeavesUserManagedSiblingsAlone(): void
    {
        // Auto on a non-Local storage means the extension is OFF for this
        // storage — we are not the authority on any .webp files there.
        // Replace/delete events must not touch user-managed siblings.
        [$autoStorage, $autoBasePath] = $this->createSecondaryStorage(mode: 0);
        \copy(self::FIXTURE_PNG, $autoBasePath . '/manual.png');
        \file_put_contents($autoBasePath . '/manual.png.webp', 'user-managed-webp');
        $original = $autoStorage->getFile('manual.png');

        $replacement = $this->instancePath . '/replacement.png';
        \copy(self::FIXTURE_PNG, $replacement);
        $autoStorage->replaceFile($original, $replacement);

        self::assertFileExists($autoBasePath . '/manual.png.webp', 'User-managed .webp must survive replace under Auto mode');
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
        [$this->storage, $this->storageBasePath] = $this->createSecondaryStorage(mode: 1);
    }

    /**
     * @return array{0: ResourceStorage, 1: string} [storage, basePath]
     */
    private function createSecondaryStorage(int $mode): array
    {
        $basePath = $this->instancePath . '/fileadmin-fake-' . uniqid();
        \mkdir($basePath, 0o775, true);

        $connection = $this->getConnectionPool()->getConnectionForTable('sys_file_storage');
        $row = [
            'name' => 'Fake remote',
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
        $uid = (int) $connection->lastInsertId('sys_file_storage');

        // Bypass StorageRepository's local cache, which was primed before
        // this fresh row was inserted. getStorageObject accepts row data
        // directly when present.
        $row['uid'] = $uid;
        $storage = GeneralUtility::makeInstance(StorageRepository::class)
            ->getStorageObject($uid, $row);

        return [$storage, $basePath];
    }

    private function placeImage(string $name): File
    {
        \copy(self::FIXTURE_PNG, $this->storageBasePath . '/' . $name);

        return $this->storage->getFile($name);
    }

    private function placeBytes(string $name, string $bytes): File
    {
        \file_put_contents($this->storageBasePath . '/' . $name, $bytes);

        return $this->storage->getFile($name);
    }
}
