<?php

declare(strict_types=1);

namespace Plan2net\Webp\Service;

use Plan2net\Webp\Converter\Converter;
use Plan2net\Webp\Converter\Exception\ConvertedFileLargerThanOriginalException;
use Plan2net\Webp\Converter\Exception\UnsupportedFormatException;
use Plan2net\Webp\Converter\Exception\WillNotRetryWithConfigurationException;
use Plan2net\Webp\Converter\MultiFormatConverter;
use Plan2net\Webp\Format\OutputFormat;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Resource\ProcessedFileRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class SiblingGenerator implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly Configuration $configuration,
        private readonly FailedAttemptsRepository $failedAttempts,
        private readonly ProcessedFileRepository $processedFileRepository,
        private readonly ProcessedFileWriter $processedFileWriter,
    ) {
    }

    /**
     * Generate every enabled sibling format for the given source. Each format
     * gets its own ProcessedFile row; failures in one format never block the
     * others. The incoming $sourceVariant (TYPO3's variant row, or the original
     * File when no real transformation happened) is NOT mutated.
     */
    public function process(
        File $originalFile,
        FileInterface $sourceVariant,
        string $taskType,
        array $taskConfiguration,
        ?OutputFormat $onlyFormat = null,
    ): void {
        if (null !== OutputFormat::tryFrom(\strtolower($originalFile->getExtension()))) {
            return;
        }

        $sourceLocalPath = $sourceVariant->getForLocalProcessing(false);
        if (!@\is_file($sourceLocalPath)) {
            return;
        }

        $mimeType = $originalFile->getMimeType();
        $formats = null === $onlyFormat
            ? $this->configuration->getEnabledFormats()
            : [$onlyFormat];

        foreach ($formats as $format) {
            if (!$this->configuration->isSupportedMimeTypeFor($format, $mimeType)) {
                continue;
            }
            $this->processFormat($originalFile, $sourceVariant, $sourceLocalPath, $mimeType, $taskType, $taskConfiguration, $format);
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
     * @throws ConvertedFileLargerThanOriginalException
     * @throws UnsupportedFormatException
     */
    public function convertFilePath(string $sourcePath, string $targetPath, string $mimeType, OutputFormat $format = OutputFormat::Webp): int
    {
        $converterClass = $this->configuration->getConverterFor($format);
        if ('' === $converterClass) {
            throw new \RuntimeException(\sprintf('No %s converter configured. Please check extension configuration.', $format->value));
        }

        $parameters = $this->configuration->getParametersFor($format, $mimeType);
        if (null === $parameters || '' === $parameters) {
            throw new \InvalidArgumentException(\sprintf('No options given for adapter "%s", mime "%s", format %s (file "%s").', $converterClass, $mimeType, $format->value, $sourcePath));
        }

        $converter = GeneralUtility::makeInstance($converterClass, $parameters, $this->configuration);
        if ($converter instanceof MultiFormatConverter) {
            $converter->convertTo($sourcePath, $targetPath, $format);
        } elseif ($converter instanceof Converter) {
            if (OutputFormat::Webp !== $format) {
                throw new UnsupportedFormatException(\sprintf('Converter %s does not implement MultiFormatConverter; cannot produce %s.', $converterClass, $format->value));
            }
            $converter->convert($sourcePath, $targetPath);
        } else {
            throw new \RuntimeException(\sprintf('Converter class %s does not implement Plan2net\\Webp\\Converter\\Converter.', $converterClass));
        }

        $sourceSize = (int) @\filesize($sourcePath);
        $targetSize = (int) @\filesize($targetPath);
        if ($targetSize > 0 && $sourceSize > 0 && $sourceSize <= $targetSize) {
            @\unlink($targetPath);

            throw new ConvertedFileLargerThanOriginalException(\sprintf('Converted file (%s) is larger (%d vs. %d) than the original (%s).', $targetPath, $targetSize, $sourceSize, $sourcePath));
        }

        return $targetSize;
    }

    private function processFormat(
        File $originalFile,
        FileInterface $sourceVariant,
        string $sourceLocalPath,
        string $mimeType,
        string $taskType,
        array $taskConfiguration,
        OutputFormat $format,
    ): void {
        $formatConfiguration = $taskConfiguration + ['format' => $format->value, 'webp' => true];
        $formatRow = $this->processedFileRepository->findOneByOriginalFileAndTaskTypeAndConfiguration(
            $originalFile,
            $taskType,
            $formatConfiguration,
        );
        if (!$this->needsReprocessing($formatRow)) {
            return;
        }

        $parameters = $this->configuration->getParametersFor($format, $mimeType);
        $fileUid = (int) $originalFile->getUid();
        if (null !== $parameters && $this->failedAttempts->wasAttempted($fileUid, $parameters, $format)) {
            $this->logger?->notice(\sprintf('webp: skipping %s for file "%s" — prior attempt failed with same configuration.', $format->value, $sourceLocalPath));

            return;
        }

        $tempTarget = GeneralUtility::tempnam(\sprintf('sibling-%s-', $format->value), $format->suffix());
        try {
            $fileSize = $this->convertFilePath($sourceLocalPath, $tempTarget, $mimeType, $format);

            $formatRow->setName($sourceVariant->getName() . $format->suffix());
            $formatRow->setIdentifier($sourceVariant->getIdentifier() . $format->suffix());

            $this->publishToStorage($sourceVariant, $formatRow, $tempTarget);
            $formatRow->updateProperties([
                'width' => (int) $sourceVariant->getProperty('width'),
                'height' => (int) $sourceVariant->getProperty('height'),
                'size' => $fileSize,
            ]);
            $this->processedFileWriter->add($formatRow, $taskType, $formatConfiguration);
        } catch (UnsupportedFormatException $e) {
            $this->logger?->warning(\sprintf('webp: %s converter cannot produce %s — skipping (%s).', $this->configuration->getConverterFor($format), $format->value, $e->getMessage()));
        } catch (ConvertedFileLargerThanOriginalException $e) {
            if (null !== $parameters) {
                $this->failedAttempts->record($fileUid, $parameters, $format);
            }
            $this->removeExistingSibling($formatRow, $sourceVariant, $format);
            $this->logger?->warning($e->getMessage());
        } catch (WillNotRetryWithConfigurationException $e) {
            $this->logger?->notice($e->getMessage());
        } catch (\Throwable $e) {
            $this->logger?->error(\sprintf('webp: %s conversion of "%s" failed: %s', $format->value, $originalFile->getIdentifier(), $e->getMessage()));
        } finally {
            GeneralUtility::unlink_tempfile($tempTarget);
        }
    }

    /**
     * Drops the sibling DB row (if persisted) and unlinks the on-disk file so
     * the conversion target is free for the next attempt.
     */
    private function removeExistingSibling(ProcessedFile $formatRow, FileInterface $sourceVariant, OutputFormat $format): void
    {
        if (!$formatRow->isNew()) {
            try {
                $formatRow->delete(true);
            } catch (\Throwable) {
            }
        }
        $localSiblingPath = $sourceVariant->getForLocalProcessing(false) . $format->suffix();
        if (@\is_file($localSiblingPath)) {
            @\unlink($localSiblingPath);
        }
    }

    private function publishToStorage(FileInterface $sourceFile, ProcessedFile $processedFile, string $tempTarget): void
    {
        $storage = $sourceFile->getStorage();

        if ($storage->isWithinProcessingFolder($sourceFile->getIdentifier())) {
            $processingFolder = $storage->getProcessingFolder($processedFile->getOriginalFile());
            $storage->updateProcessedFile($tempTarget, $processedFile, $processingFolder);

            return;
        }

        $folder = $sourceFile->getParentFolder();
        if (!$folder instanceof Folder) {
            throw new \RuntimeException(\sprintf('Cannot publish sibling next to "%s": parent folder is not writable', $sourceFile->getIdentifier()));
        }
        $newFile = $folder->addFile($tempTarget, $processedFile->getName(), self::replaceConflictMode());
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
}
