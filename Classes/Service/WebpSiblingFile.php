<?php

declare(strict_types=1);

namespace Plan2net\Webp\Service;

use TYPO3\CMS\Core\Resource\AbstractFile;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\ResourceStorage;

final class WebpSiblingFile
{
    /** @var \WeakMap<FileInterface, string> */
    private \WeakMap $capturedOldSiblings;

    public function __construct()
    {
        $this->capturedOldSiblings = new \WeakMap();
    }

    public function captureBeforeMove(FileInterface $file): void
    {
        if (!$this->isLocalWritable($file->getStorage())) {
            return;
        }
        $this->capturedOldSiblings[$file] = $file->getForLocalProcessing(false) . '.webp';
    }

    public function relocateAfterMove(FileInterface $fileAtNewLocation): void
    {
        $oldSibling = $this->capturedOldSiblings[$fileAtNewLocation] ?? null;
        unset($this->capturedOldSiblings[$fileAtNewLocation]);
        if (null === $oldSibling) {
            return;
        }
        if (!$this->isLocalWritable($fileAtNewLocation->getStorage())) {
            return;
        }
        if (!\is_file($oldSibling)) {
            return;
        }
        $newSibling = $fileAtNewLocation->getForLocalProcessing(false) . '.webp';
        if ($oldSibling === $newSibling) {
            return;
        }
        if (@\rename($oldSibling, $newSibling)) {
            return;
        }
        // Rename failed (e.g. cross-filesystem). Clear both ends so neither
        // location serves stale content; lazy regeneration recreates the
        // sibling at the new path on the next render.
        $this->unlinkIfExists($oldSibling);
        $this->unlinkIfExists($newSibling);
    }

    public function deleteForReplacedFile(FileInterface $file): void
    {
        if (!$this->isLocalWritable($file->getStorage())) {
            return;
        }
        $this->unlinkIfExists($file->getForLocalProcessing(false) . '.webp');
    }

    public function deleteForDeletedFile(FileInterface $file): void
    {
        $storage = $file->getStorage();
        if (!$this->isLocalWritable($storage)) {
            return;
        }
        // ResourceStorage::deleteFile() routes through moveFile() when the
        // storage has a recycler and only flips setDeleted() on a physical
        // delete. In the recycler case our BeforeFileMoved/AfterFileMoved
        // pair has already relocated the sibling alongside the file, so
        // leaving it there is what users expect on restore.
        if ($file instanceof AbstractFile && !$file->isDeleted()) {
            return;
        }
        $this->unlinkIfExists($storage->getFileForLocalProcessing($file, false) . '.webp');
    }

    private function unlinkIfExists(string $path): void
    {
        if (\is_file($path)) {
            @\unlink($path);
        }
    }

    private function isLocalWritable(?ResourceStorage $storage): bool
    {
        if (null === $storage || 0 === $storage->getUid()) {
            return false;
        }

        return 'Local' === $storage->getDriverType() && $storage->isWritable();
    }
}
