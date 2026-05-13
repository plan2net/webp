<?php

declare(strict_types=1);

namespace Plan2net\Webp\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Plan2net\Webp\Service\WebpSiblingFile;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\ResourceStorage;

final class WebpSiblingFileTest extends TestCase
{
    private string $tempDir;

    #[Test]
    public function deleteForDeletedFileRemovesSiblingWhenItExists(): void
    {
        $originalPath = $this->tempDir . '/fileadmin/photo.png';
        $siblingPath = $originalPath . '.webp';
        $this->touchAtPath($siblingPath);

        $storage = $this->localWritableStorage(1);
        $storage->method('getFileForLocalProcessing')->willReturn($originalPath);
        $file = $this->deletedFile($storage);

        (new WebpSiblingFile())->deleteForDeletedFile($file);

        self::assertFileDoesNotExist($siblingPath);
    }

    #[Test]
    public function deleteForDeletedFileIsNoOpWhenSiblingMissing(): void
    {
        $originalPath = $this->tempDir . '/fileadmin/photo.png';

        $storage = $this->localWritableStorage(1);
        $storage->method('getFileForLocalProcessing')->willReturn($originalPath);
        $file = $this->deletedFile($storage);

        (new WebpSiblingFile())->deleteForDeletedFile($file);

        self::assertFileDoesNotExist($originalPath . '.webp');
    }

    #[Test]
    public function deleteForDeletedFileSkipsWhenFileMovedToRecycler(): void
    {
        // ResourceStorage::deleteFile() reroutes through moveFile() when a
        // recycler exists and skips $file->setDeleted() in that case. Our
        // BeforeFileMoved/AfterFileMoved pair has already moved the sibling
        // alongside the file; touching it again here would silently strip
        // the sibling on every recycler-delete and surprise users on restore.
        $recyclerPath = $this->tempDir . '/fileadmin/_recycler_/photo.png';
        $recyclerSibling = $recyclerPath . '.webp';
        $this->touchAtPath($recyclerSibling);

        $storage = $this->localWritableStorage(1);
        $storage->method('getFileForLocalProcessing')->willReturn($recyclerPath);
        $file = $this->createMock(File::class);
        $file->method('getStorage')->willReturn($storage);
        $file->method('isDeleted')->willReturn(false);

        (new WebpSiblingFile())->deleteForDeletedFile($file);

        self::assertFileExists($recyclerSibling);
    }

    #[Test]
    public function deleteForReplacedFileRemovesSiblingWhenItExists(): void
    {
        $originalPath = $this->tempDir . '/fileadmin/photo.png';
        $siblingPath = $originalPath . '.webp';
        $this->touchAtPath($siblingPath);

        $storage = $this->localWritableStorage(1);
        $file = $this->createMock(FileInterface::class);
        $file->method('getStorage')->willReturn($storage);
        $file->method('getForLocalProcessing')->with(false)->willReturn($originalPath);

        (new WebpSiblingFile())->deleteForReplacedFile($file);

        self::assertFileDoesNotExist($siblingPath);
    }

    #[Test]
    public function deleteIsNoOpForNonLocalStorage(): void
    {
        $originalPath = $this->tempDir . '/fileadmin/photo.png';
        $siblingPath = $originalPath . '.webp';
        $this->touchAtPath($siblingPath);

        $storage = $this->createMock(ResourceStorage::class);
        $storage->method('getUid')->willReturn(2);
        $storage->method('getDriverType')->willReturn('S3');
        $storage->method('isWritable')->willReturn(true);
        $file = $this->deletedFile($storage);

        (new WebpSiblingFile())->deleteForDeletedFile($file);

        self::assertFileExists($siblingPath);
    }

    #[Test]
    public function deleteIsNoOpForFallbackStorage(): void
    {
        $originalPath = $this->tempDir . '/fileadmin/photo.png';
        $siblingPath = $originalPath . '.webp';
        $this->touchAtPath($siblingPath);

        $storage = $this->createMock(ResourceStorage::class);
        $storage->method('getUid')->willReturn(0);
        $storage->method('getDriverType')->willReturn('Local');
        $storage->method('isWritable')->willReturn(true);
        $file = $this->deletedFile($storage);

        (new WebpSiblingFile())->deleteForDeletedFile($file);

        self::assertFileExists($siblingPath);
    }

    #[Test]
    public function relocateAfterMoveRenamesCapturedSibling(): void
    {
        $oldPath = $this->tempDir . '/fileadmin/old/photo.png';
        $newPath = $this->tempDir . '/fileadmin/new/photo.png';
        $oldSibling = $oldPath . '.webp';
        $newSibling = $newPath . '.webp';
        $this->touchAtPath($oldSibling);
        \mkdir(\dirname($newPath), 0o777, true);

        $file = $this->fileAtConsecutivePaths($oldPath, $newPath, $this->localWritableStorage(1));

        $helper = new WebpSiblingFile();
        $helper->captureBeforeMove($file);
        $helper->relocateAfterMove($file);

        self::assertFileDoesNotExist($oldSibling);
        self::assertFileExists($newSibling);
    }

    #[Test]
    public function relocateAfterMoveHandlesRenameDuringMove(): void
    {
        $oldPath = $this->tempDir . '/fileadmin/old/photo.png';
        $newPath = $this->tempDir . '/fileadmin/new/photo_01.png';
        $oldSibling = $oldPath . '.webp';
        $newSibling = $newPath . '.webp';
        $this->touchAtPath($oldSibling);
        \mkdir(\dirname($newPath), 0o777, true);

        $file = $this->fileAtConsecutivePaths($oldPath, $newPath, $this->localWritableStorage(1));

        $helper = new WebpSiblingFile();
        $helper->captureBeforeMove($file);
        $helper->relocateAfterMove($file);

        self::assertFileDoesNotExist($oldSibling);
        self::assertFileExists($newSibling);
    }

    #[Test]
    public function relocateAfterMoveFallsBackToUnlinkWhenRenameFails(): void
    {
        $oldPath = $this->tempDir . '/fileadmin/old/photo.png';
        $newPath = $this->tempDir . '/missing/photo.png';
        $oldSibling = $oldPath . '.webp';
        $this->touchAtPath($oldSibling);

        $file = $this->fileAtConsecutivePaths($oldPath, $newPath, $this->localWritableStorage(1));

        $helper = new WebpSiblingFile();
        $helper->captureBeforeMove($file);
        $helper->relocateAfterMove($file);

        self::assertFileDoesNotExist($oldSibling);
        self::assertFileDoesNotExist($newPath . '.webp');
    }

    #[Test]
    public function relocateAfterMoveIsNoOpWhenNothingCaptured(): void
    {
        $oldPath = $this->tempDir . '/fileadmin/old/photo.png';
        $oldSibling = $oldPath . '.webp';
        $this->touchAtPath($oldSibling);

        $file = $this->createMock(FileInterface::class);
        $file->method('getStorage')->willReturn($this->localWritableStorage(1));
        $file->method('getForLocalProcessing')->with(false)->willReturn($oldPath);

        (new WebpSiblingFile())->relocateAfterMove($file);

        self::assertFileExists($oldSibling);
    }

    #[Test]
    public function relocateAfterMoveIsNoOpWhenCapturedSiblingMissing(): void
    {
        $oldPath = $this->tempDir . '/fileadmin/old/photo.png';
        $newPath = $this->tempDir . '/fileadmin/new/photo.png';
        \mkdir(\dirname($newPath), 0o777, true);

        $file = $this->fileAtConsecutivePaths($oldPath, $newPath, $this->localWritableStorage(1));

        $helper = new WebpSiblingFile();
        $helper->captureBeforeMove($file);
        $helper->relocateAfterMove($file);

        self::assertFileDoesNotExist($oldPath . '.webp');
        self::assertFileDoesNotExist($newPath . '.webp');
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = \sys_get_temp_dir() . '/webp-sibling-' . \bin2hex(\random_bytes(6));
        \mkdir($this->tempDir . '/fileadmin', 0o777, true);
    }

    protected function tearDown(): void
    {
        $this->removeRecursive($this->tempDir);
        parent::tearDown();
    }

    /**
     * @return ResourceStorage&MockObject
     */
    private function localWritableStorage(int $uid): ResourceStorage
    {
        $storage = $this->createMock(ResourceStorage::class);
        $storage->method('getStorageRecord')->willReturn(['uid' => $uid]);
        $storage->method('getDriverType')->willReturn('Local');
        $storage->method('isWritable')->willReturn(true);
        $storage->method('getUid')->willReturn($uid);

        return $storage;
    }

    /**
     * @return File&MockObject
     */
    private function deletedFile(ResourceStorage $storage): File
    {
        $file = $this->createMock(File::class);
        $file->method('getStorage')->willReturn($storage);
        $file->method('isDeleted')->willReturn(true);

        return $file;
    }

    /**
     * @return FileInterface&MockObject
     */
    private function fileAtConsecutivePaths(string $oldPath, string $newPath, ResourceStorage $storage): FileInterface
    {
        $file = $this->createMock(FileInterface::class);
        $file->method('getStorage')->willReturn($storage);
        $file->method('getForLocalProcessing')->with(false)->willReturnOnConsecutiveCalls($oldPath, $newPath);

        return $file;
    }

    private function touchAtPath(string $path): void
    {
        $dir = \dirname($path);
        if (!\is_dir($dir)) {
            \mkdir($dir, 0o777, true);
        }
        \file_put_contents($path, 'fake-webp');
    }

    private function removeRecursive(string $path): void
    {
        if (!\file_exists($path)) {
            return;
        }
        if (\is_dir($path)) {
            foreach (\scandir($path) ?: [] as $entry) {
                if ('.' === $entry || '..' === $entry) {
                    continue;
                }
                $this->removeRecursive($path . '/' . $entry);
            }
            \rmdir($path);

            return;
        }
        \unlink($path);
    }
}
