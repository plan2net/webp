<?php

declare(strict_types=1);

namespace Plan2net\Webp\EventListener;

use Plan2net\Webp\Domain\Queue\ConversionQueueRepository;
use Plan2net\Webp\Format\OutputFormat;
use Plan2net\Webp\Service\Configuration;
use Plan2net\Webp\Service\PathMatcher;
use Plan2net\Webp\Service\SiblingGenerator;
use Plan2net\Webp\Service\StorageSiblingMode;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Resource\Event\AfterFileProcessingEvent;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\FileReference;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Resource\ProcessedFileRepository;

#[AsEventListener('webp.after-file-processing')]
final class AfterFileProcessing implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly PathMatcher $pathMatcher,
        private readonly SiblingGenerator $siblingGenerator,
        private readonly Configuration $configuration,
        private readonly ConversionQueueRepository $queueRepository,
        private readonly ProcessedFileRepository $processedFileRepository,
    ) {
    }

    public function __invoke(AfterFileProcessingEvent $event): void
    {
        $processedFile = $event->getProcessedFile();
        $file = $event->getFile();

        if (!$this->shouldProcess($event->getTaskType(), $processedFile)) {
            return;
        }

        $originalFile = $file instanceof FileReference ? $file->getOriginalFile() : $file;
        if (!$originalFile instanceof File) {
            return;
        }

        // When no real transformation happened the variant's storage record is
        // stale and points at the original; use the original FAL File directly
        // so identifier/parent-folder lookups resolve correctly.
        $sourceVariant = $processedFile;
        $taskConfiguration = $event->getConfiguration();
        if (!$this->isFileInProcessingFolder($processedFile)) {
            $sourceVariant = $originalFile;
            $taskConfiguration = [];
        }

        if ($this->configuration->isAsync()) {
            $this->enqueueEnabledFormats($originalFile, $processedFile, $sourceVariant, $event->getTaskType(), $taskConfiguration);

            return;
        }

        try {
            $this->siblingGenerator->process($originalFile, $sourceVariant, $event->getTaskType(), $taskConfiguration);
        } catch (\Throwable $e) {
            $this->logger?->error(\sprintf('webp: sibling generation aborted for "%s": %s', $sourceVariant->getIdentifier(), $e->getMessage()));
        }
    }

    /**
     * Enqueues one row per enabled format whose mime list accepts this file.
     * The queue worker re-dispatches each entry with its own $entry->format,
     * so a single drain produces exactly the formats that were enqueued.
     */
    private function enqueueEnabledFormats(File $originalFile, ProcessedFile $processedFile, FileInterface $sourceVariant, string $taskType, array $taskConfiguration): void
    {
        $processedFileId = $processedFile->usesOriginalFile() ? 0 : (int) $processedFile->getUid();
        $mimeType = $originalFile->getMimeType();

        foreach ($this->configuration->getEnabledFormats() as $format) {
            if (!$this->configuration->isSupportedMimeTypeFor($format, $mimeType)) {
                continue;
            }
            if (!$this->configuration->isFormatRunnable($format)) {
                continue;
            }
            $formatConfiguration = $taskConfiguration + ['format' => $format->value, 'webp' => true];
            $formatRow = $this->processedFileRepository->findOneByOriginalFileAndTaskTypeAndConfiguration(
                $originalFile,
                $taskType,
                $formatConfiguration,
            );
            if (!$this->siblingGenerator->needsReprocessing($formatRow)) {
                continue;
            }
            try {
                $this->queueRepository->enqueue(
                    (int) $originalFile->getUid(),
                    $processedFileId,
                    $taskType,
                    $formatConfiguration,
                    $format,
                );
            } catch (\Doctrine\DBAL\Exception $exception) {
                $this->logger?->notice(\sprintf('webp: queue table unavailable for %s, falling back to synchronous conversion: %s', $format->value, $exception->getMessage()));
                try {
                    $this->siblingGenerator->process($originalFile, $sourceVariant, $taskType, $taskConfiguration, $format);
                } catch (\Throwable $inner) {
                    $this->logger?->error(\sprintf('webp: sync fallback for %s also failed: %s', $format->value, $inner->getMessage()));
                }
            }
        }
    }

    private function shouldProcess(string $taskType, ProcessedFile $processedFile): bool
    {
        if ('Image.CropScaleMask' !== $taskType) {
            return false;
        }

        $originalFile = $processedFile->getOriginalFile();
        if (OutputFormat::isOutputExtension($originalFile->getExtension())) {
            return false;
        }

        if (!StorageSiblingMode::isEnabledFor($processedFile->getStorage())) {
            return false;
        }

        if (!$this->anyEnabledFormatAcceptsMimeType($originalFile->getMimeType())) {
            return false;
        }

        if (!$this->configuration->isConvertAll() && !$this->isFileInProcessingFolder($processedFile)) {
            return false;
        }

        if ($this->originalFileIsInExcludedDirectory($originalFile)) {
            return false;
        }

        return true;
    }

    private function anyEnabledFormatAcceptsMimeType(string $mimeType): bool
    {
        foreach ($this->configuration->getEnabledFormats() as $format) {
            if (!$this->configuration->isFormatRunnable($format)) {
                continue;
            }
            if ($this->configuration->isSupportedMimeTypeFor($format, $mimeType)) {
                return true;
            }
        }

        return false;
    }

    private function isFileInProcessingFolder(ProcessedFile $file): bool
    {
        $processingFolder = $file->getStorage()->getProcessingFolder();

        return $this->pathMatcher->matches($file->getIdentifier(), $processingFolder->getIdentifier());
    }

    private function originalFileIsInExcludedDirectory(FileInterface $file): bool
    {
        $storageBasePath = $file->getStorage()->getConfiguration()['basePath'] ?? '';
        $filePath = rtrim($storageBasePath, '/') . '/' . ltrim($file->getIdentifier(), '/');

        return $this->pathMatcher->matchesAny($filePath, $this->configuration->getExcludeDirectories());
    }
}
