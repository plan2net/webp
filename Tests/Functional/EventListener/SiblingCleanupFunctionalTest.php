<?php

declare(strict_types=1);

namespace Plan2net\Webp\Tests\Functional\EventListener;

use PHPUnit\Framework\Attributes\Test;
use Plan2net\Webp\Converter\PhpGdConverter;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class SiblingCleanupFunctionalTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = [
        'install',
        'scheduler',
    ];

    protected array $testExtensionsToLoad = [
        'plan2net/webp',
    ];

    private string $fileadminPath;
    private string $targetFolderName = 'images';

    #[Test]
    public function siblingFollowsRenamedSourceFile(): void
    {
        $file = $this->getFile(1);
        $oldSibling = $this->fileadminPath . 'tiny.png.webp';
        $newSibling = $this->fileadminPath . 'renamed.png.webp';
        \file_put_contents($oldSibling, 'fake-webp');

        $file->getStorage()->renameFile($file, 'renamed.png');

        self::assertFileDoesNotExist($oldSibling);
        self::assertFileExists($newSibling);
    }

    #[Test]
    public function siblingMovesWithSourceFile(): void
    {
        $file = $this->getFile(1);
        $sibling = $this->fileadminPath . 'tiny.png.webp';
        \file_put_contents($sibling, 'fake-webp');

        $targetFolder = $file->getStorage()->getFolder('/' . $this->targetFolderName . '/');
        $file->getStorage()->moveFile($file, $targetFolder);

        self::assertFileDoesNotExist($sibling);
        self::assertFileExists($this->fileadminPath . $this->targetFolderName . '/tiny.png.webp');
    }

    #[Test]
    public function siblingIsDeletedWhenSourceFileIsDeleted(): void
    {
        $file = $this->getFile(1);
        $sibling = $this->fileadminPath . 'tiny.png.webp';
        \file_put_contents($sibling, 'fake-webp');

        $file->delete();

        self::assertFileDoesNotExist($sibling);
    }

    #[Test]
    public function siblingIsDeletedWhenSourceFileIsReplaced(): void
    {
        $file = $this->getFile(1);
        $sibling = $this->fileadminPath . 'tiny.png.webp';
        \file_put_contents($sibling, 'fake-webp');

        $replacement = $this->instancePath . '/replacement.png';
        \copy(__DIR__ . '/../Fixtures/Images/tiny.png', $replacement);

        $file->getStorage()->replaceFile($file, $replacement);

        self::assertFileDoesNotExist($sibling);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->get(StorageRepository::class)->findAll();

        $storageRow = $this->getConnectionPool()
            ->getConnectionForTable('sys_file_storage')
            ->select(['uid', 'driver'], 'sys_file_storage', ['uid' => 1])
            ->fetchAssociative();
        self::assertNotFalse($storageRow, 'Expected default fileadmin storage (UID 1) to exist');
        self::assertSame('Local', $storageRow['driver']);

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/Database/sys_file.csv');

        $this->fileadminPath = $this->instancePath . '/fileadmin/';
        if (!\is_dir($this->fileadminPath)) {
            \mkdir($this->fileadminPath, 0o777, true);
        }
        if (!\is_dir($this->fileadminPath . $this->targetFolderName)) {
            \mkdir($this->fileadminPath . $this->targetFolderName, 0o777, true);
        }
        \copy(__DIR__ . '/../Fixtures/Images/tiny.png', $this->fileadminPath . 'tiny.png');

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
        ];
    }

    private function getFile(int $uid): File
    {
        return $this->get(ResourceFactory::class)->getFileObject($uid);
    }
}
