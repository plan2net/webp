<?php

declare(strict_types=1);

namespace Plan2net\Webp\Service;

use Plan2net\Webp\Converter\Converter;
use Plan2net\Webp\Converter\Exception\ConvertedFileLargerThanOriginalException;
use Plan2net\Webp\Converter\Exception\WillNotRetryWithConfigurationException;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class Webp
{
    public function __construct(
        private readonly Configuration $configuration,
        private readonly FailedAttemptsRepository $failedAttempts,
    ) {
    }

    /**
     * @param FileInterface|File $originalFile
     *
     * @throws ConvertedFileLargerThanOriginalException
     * @throws WillNotRetryWithConfigurationException
     */
    public function process(FileInterface $originalFile, ProcessedFile $processedFile): void
    {
        if ('webp' === $originalFile->getExtension()) {
            return;
        }

        $processedFile->setName($originalFile->getName() . '.webp');
        $processedFile->setIdentifier($originalFile->getIdentifier() . '.webp');

        $sourceLocalPath = $originalFile->getForLocalProcessing(false);
        if (!@\is_file($sourceLocalPath)) {
            return;
        }

        $mimeType = $originalFile->getMimeType();
        $parameters = $this->getParametersForMimeType($mimeType);
        $fileUid = (int) $originalFile->getUid();
        if (null !== $parameters && $this->failedAttempts->wasAttempted($fileUid, $parameters)) {
            throw new WillNotRetryWithConfigurationException(\sprintf('Conversion for file "%s" failed before! Will not retry with this configuration!', $sourceLocalPath));
        }

        $tempTarget = GeneralUtility::tempnam('webp-', '.webp');
        try {
            $fileSize = $this->convertFilePath($sourceLocalPath, $tempTarget, $mimeType);
            $this->publishToStorage($originalFile, $processedFile, $tempTarget);

            $processedFile->updateProperties([
                'width' => $originalFile->getProperty('width'),
                'height' => $originalFile->getProperty('height'),
                'size' => $fileSize,
            ]);
        } catch (ConvertedFileLargerThanOriginalException $e) {
            if (null !== $parameters) {
                $this->failedAttempts->record($fileUid, $parameters);
            }
            throw $e;
        } finally {
            GeneralUtility::unlink_tempfile($tempTarget);
        }
    }

    public function needsReprocessing(ProcessedFile $processedFile): bool
    {
        return $processedFile->isNew()
            || (!$processedFile->usesOriginalFile() && !$processedFile->exists())
            || $processedFile->isOutdated();
    }

    /**
     * Low-level converter invocation usable without a FAL `File` (e.g., folder-mode sweeps).
     *
     * @return int Size in bytes of the converted file
     *
     * @throws ConvertedFileLargerThanOriginalException if the WebP output is not smaller than the source — the oversize target is removed before the exception is thrown
     */
    public function convertFilePath(string $sourcePath, string $targetPath, string $mimeType): int
    {
        $converterClass = $this->configuration->getConverter();
        if (empty($converterClass)) {
            throw new \RuntimeException('No WebP converter configured. Please check extension configuration.');
        }

        $parameters = $this->getParametersForMimeType($mimeType);
        if (empty($parameters)) {
            throw new \InvalidArgumentException(\sprintf('No options given for adapter "%s" and mime type "%s" (file "%s")!', $converterClass, $mimeType, $sourcePath));
        }

        /** @var Converter $converter */
        $converter = GeneralUtility::makeInstance($converterClass, $parameters, $this->configuration);
        $converter->convert($sourcePath, $targetPath);

        $sourceSize = (int) @\filesize($sourcePath);
        $targetSize = (int) @\filesize($targetPath);
        if ($targetSize > 0 && $sourceSize > 0 && $sourceSize <= $targetSize) {
            @\unlink($targetPath);

            throw new ConvertedFileLargerThanOriginalException(\sprintf('Converted file (%s) is larger (%d vs. %d) than the original (%s)!', $targetPath, $targetSize, $sourceSize, $sourcePath));
        }

        return $targetSize;
    }

    private function publishToStorage(FileInterface $sourceFile, ProcessedFile $processedFile, string $tempTarget): void
    {
        $storage = $sourceFile->getStorage();

        if ($storage->isWithinProcessingFolder($sourceFile->getIdentifier())) {
            // The .webp lives alongside the processed variant inside the
            // (potentially nested) processing folder. We call
            // ResourceStorage::updateProcessedFile() directly — same method
            // ProcessedFile::updateWithLocalFile() uses internally — to avoid
            // a v12 path that NPEs on getProperties() for fresh ProcessedFile
            // instances. ResourceStorage::addFile() isn't usable either: its
            // post-write getFileByIdentifier() routes through
            // ProcessedFileRepository inside the processing folder and
            // returns null until our row is written downstream.
            $processingFolder = $storage->getProcessingFolder($processedFile->getOriginalFile());
            $storage->updateProcessedFile($tempTarget, $processedFile, $processingFolder);

            return;
        }

        // The .webp lives alongside the original in the source folder.
        // REPLACE conflict mode is atomic at the driver level (PHP copy()
        // overwrites the destination); the previous sibling survives a
        // transient upload failure.
        $folder = $sourceFile->getParentFolder();
        $targetName = $sourceFile->getName() . '.webp';
        $newFile = $folder->addFile($tempTarget, $targetName, self::replaceConflictMode());
        $processedFile->setIdentifier($newFile->getIdentifier());
    }

    /**
     * TYPO3 v13/v14 ship DuplicationBehavior as a string-backed enum at
     * \TYPO3\CMS\Core\Resource\Enum\DuplicationBehavior; v12 has a class with
     * constants at \TYPO3\CMS\Core\Resource\DuplicationBehavior. The
     * extension supports all three so we resolve at runtime.
     */
    private static function replaceConflictMode(): mixed
    {
        if (\enum_exists('TYPO3\\CMS\\Core\\Resource\\Enum\\DuplicationBehavior')) {
            return \TYPO3\CMS\Core\Resource\Enum\DuplicationBehavior::REPLACE;
        }

        return \TYPO3\CMS\Core\Resource\DuplicationBehavior::REPLACE;
    }

    private function getParametersForMimeType(string $mimeType): ?string
    {
        $parameters = \explode('|', $this->configuration->getParameters());
        foreach ($parameters as $parameter) {
            $typeAndOptions = \explode('::', $parameter, 2);
            $type = $typeAndOptions[0] ?? null;
            $options = $typeAndOptions[1] ?? null;
            // Fallback to old options format
            if (empty($options)) {
                return $type;
            }
            if ($type === $mimeType) {
                return $options;
            }
        }

        return null;
    }
}
