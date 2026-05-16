<?php

declare(strict_types=1);

namespace Plan2net\Webp\Service;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Resource\AbstractFile;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Resource\StorageRepository;

final class WebpSiblingFile implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /** @var \WeakMap<FileInterface, array{0: int, 1: string}> */
    private \WeakMap $capturedOldSiblings;

    public function __construct(
        private readonly StorageRepository $storageRepository,
    ) {
        $this->capturedOldSiblings = new \WeakMap();
    }

    public function captureBeforeMove(FileInterface $file): void
    {
        if ($this->shouldSkip($file)) {
            return;
        }
        $this->capturedOldSiblings[$file] = [
            $file->getStorage()->getUid(),
            $file->getIdentifier() . '.webp',
        ];
    }

    public function relocateAfterMove(FileInterface $fileAtNewLocation): void
    {
        $captured = $this->capturedOldSiblings[$fileAtNewLocation] ?? null;
        unset($this->capturedOldSiblings[$fileAtNewLocation]);
        if (null === $captured) {
            return;
        }
        [$sourceStorageUid, $oldSiblingIdentifier] = $captured;
        $newStorage = $fileAtNewLocation->getStorage();
        $sameStorage = $sourceStorageUid === $newStorage->getUid();

        try {
            if ($sameStorage && StorageWebpMode::isEnabledFor($newStorage)) {
                $this->moveSiblingWithinStorage($newStorage, $oldSiblingIdentifier, $fileAtNewLocation);

                return;
            }
            // Cross-storage move, or same-storage with mode now Disabled:
            // captured source sibling is orphaned regardless of destination
            // mode. Lazy regeneration covers the new location on next render
            // if mode allows.
            $this->cleanupSourceSibling($sourceStorageUid, $oldSiblingIdentifier);
        } catch (\Exception $e) {
            $this->logger?->warning('webp: sibling relocation failed, falling back to source cleanup + lazy regeneration: ' . $e->getMessage());
            $this->cleanupSourceSibling($sourceStorageUid, $oldSiblingIdentifier);
        }
    }

    public function deleteForReplacedFile(FileInterface $file): void
    {
        if ($this->shouldSkip($file)) {
            return;
        }
        $this->deleteSiblingIfExists($file->getStorage(), $file->getIdentifier() . '.webp');
    }

    public function deleteForDeletedFile(FileInterface $file): void
    {
        if ($this->shouldSkip($file)) {
            return;
        }
        if ($file instanceof AbstractFile && !$file->isDeleted()) {
            // ResourceStorage::deleteFile() reroutes through moveFile() when a
            // recycler exists and only flips setDeleted() on a physical delete.
            // In the recycler case our BeforeFileMoved/AfterFileMoved pair has
            // already relocated the sibling alongside the file.
            return;
        }
        $this->deleteSiblingIfExists($file->getStorage(), $file->getIdentifier() . '.webp');
    }

    private function shouldSkip(FileInterface $file): bool
    {
        if ('webp' === $file->getExtension()) {
            return true;
        }

        // Mode-gated: don't touch siblings on storages where we're not the
        // authority. Auto on a non-Local storage means user-managed .webp
        // files are off-limits to us — we mustn't delete or move them.
        return !StorageWebpMode::isEnabledFor($file->getStorage());
    }

    private function moveSiblingWithinStorage(ResourceStorage $storage, string $oldIdentifier, FileInterface $fileAtNewLocation): void
    {
        if (!$storage->hasFile($oldIdentifier)) {
            return;
        }
        $siblingFile = $storage->getFile($oldIdentifier);
        $newFolder = $fileAtNewLocation->getParentFolder();
        $newName = $fileAtNewLocation->getName() . '.webp';

        if ($newFolder->hasFile($newName)) {
            $storage->deleteFile($newFolder->getFile($newName));
        }

        $storage->moveFile($siblingFile, $newFolder, $newName);
    }

    private function cleanupSourceSibling(int $storageUid, string $identifier): void
    {
        $storage = $this->storageRepository->findByUid($storageUid);
        if (null === $storage) {
            return;
        }
        $this->deleteSiblingIfExists($storage, $identifier);
    }

    private function deleteSiblingIfExists(ResourceStorage $storage, string $identifier): void
    {
        try {
            if ($storage->hasFile($identifier)) {
                $storage->deleteFile($storage->getFile($identifier));
            }
        } catch (\Exception $e) {
            $this->logger?->warning(sprintf('webp: failed to delete sibling "%s": %s', $identifier, $e->getMessage()));
        }
    }
}
