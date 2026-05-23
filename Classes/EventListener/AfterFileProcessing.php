<?php

declare(strict_types=1);

namespace Plan2net\Webp\EventListener;

use Plan2net\Webp\Converter\Exception\ConvertedFileLargerThanOriginalException;
use Plan2net\Webp\Converter\Exception\WillNotRetryWithConfigurationException;
use Plan2net\Webp\Domain\Queue\ConversionQueueRepository;
use Plan2net\Webp\Service\Configuration;
use Plan2net\Webp\Service\PathMatcher;
use Plan2net\Webp\Service\ProcessedFileWriter;
use Plan2net\Webp\Service\SiblingGenerator;
use Plan2net\Webp\Service\StorageSiblingMode;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Resource\Event\AfterFileProcessingEvent;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\FileReference;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Resource\ProcessedFileRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;

#[AsEventListener('webp.after-file-processing')]
final class AfterFileProcessing implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly PathMatcher $pathMatcher,
        private readonly SiblingGenerator $siblingGenerator,
        private readonly ProcessedFileRepository $processedFileRepository,
        private readonly ProcessedFileWriter $processedFileWriter,
        private readonly Configuration $configuration,
        private readonly ConversionQueueRepository $queueRepository,
    ) {
    }

    public function __invoke(AfterFileProcessingEvent $event): void
    {
        $this->processFile(
            $event->getProcessedFile(),
            $event->getFile(),
            $event->getTaskType(),
            $event->getConfiguration()
        );
    }

    /**
     * Process a file using the configured adapter to create a webp copy.
     *
     * @param FileInterface|File $file
     */
    private function processFile(
        ProcessedFile $processedFile,
        FileInterface $file,
        string $taskType,
        array $configuration,
    ): void {
        if ($this->shouldProcess($taskType, $processedFile)) {
            // Check if we are processing the original file
            if (!$this->isFileInProcessingFolder($processedFile)) {
                // In this case the processed file has the wrong storage record attached
                // and the file would not be found in the next steps,
                // so we use the original file then
                $processedFile = $file;
                // Reset configuration (file was not modified) to prevent duplicate entries
                $configuration = [];
            }

            // Normalize FileReference -> File for the repository lookup (mirrors
            // FileProcessingService::processFile in core). This satisfies v14's
            // narrowed File parameter and also fixes a latent v12/v13 bug where
            // a FileReference UID would be looked up against sys_file_processedfile.
            $originalFile = $file instanceof FileReference ? $file->getOriginalFile() : $file;
            if (!$originalFile instanceof File) {
                return;
            }

            // This will either return an existing file or create a new one
            $processedFileWebp = $this->processedFileRepository->findOneByOriginalFileAndTaskTypeAndConfiguration(
                $originalFile,
                $taskType,
                $configuration + [
                    'webp' => true,
                ]
            );
            // Check if reprocessing is required
            if (!$this->siblingGenerator->needsReprocessing($processedFileWebp)) {
                return;
            }

            if ($this->configuration->isAsync()) {
                $processedFileId = $processedFile instanceof ProcessedFile && !$processedFile->usesOriginalFile()
                    ? (int) $processedFile->getUid()
                    : 0;
                try {
                    $this->queueRepository->enqueue(
                        (int) $originalFile->getUid(),
                        $processedFileId,
                        $taskType,
                        $configuration + ['webp' => true]
                    );

                    return;
                } catch (\Doctrine\DBAL\Exception $e) {
                    $this->logger->notice(
                        \sprintf(
                            'webp: queue table unavailable, falling back to synchronous conversion: %s',
                            $e->getMessage()
                        )
                    );
                    // fall through to sync path
                }
            }

            try {
                $this->siblingGenerator->process($processedFile, $processedFileWebp);

                // This will add or update; the writer hides the v14 signature change.
                $this->processedFileWriter->add(
                    $processedFileWebp,
                    $taskType,
                    $configuration + ['webp' => true],
                );
            } catch (WillNotRetryWithConfigurationException $e) {
                $this->logger->notice($e->getMessage());
            } catch (ConvertedFileLargerThanOriginalException $e) {
                $this->logger->warning($e->getMessage());
                $this->removeProcessedFile($processedFileWebp);
            } catch (\Exception $e) {
                // Transient failures (S3 upload denied, network blip, quota,
                // driver-specific I/O) are expected on remote drivers. The
                // publish step uses atomic replace, so the previous valid
                // .webp survives — we must NOT remove the processedFile here
                // or we'd delete that good sibling. Log and move on; the
                // next render retries.
                $this->logger->error(
                    \sprintf(
                        'Failed to convert image "%s" to webp with: %s',
                        $processedFile->getIdentifier(),
                        $e->getMessage()
                    )
                );
            }
        }
    }

    private function shouldProcess(string $taskType, ProcessedFile $processedFile): bool
    {
        if ('Image.CropScaleMask' !== $taskType) {
            return false;
        }

        if ('webp' === $processedFile->getOriginalFile()->getExtension()) {
            return false;
        }

        if (!$this->configuration->isSupportedMimeType($processedFile->getOriginalFile()->getMimeType())) {
            return false;
        }

        // Convert images in any folder or only in the _processed_ folder
        $convertAllImages = $this->configuration->isConvertAll();
        if (!$convertAllImages && !$this->isFileInProcessingFolder($processedFile)) {
            return false;
        }

        if (!StorageSiblingMode::isEnabledFor($processedFile->getStorage())) {
            return false;
        }

        if ($this->originalFileIsInExcludedDirectory($processedFile->getOriginalFile())) {
            return false;
        }

        return true;
    }

    private function isFileInProcessingFolder(ProcessedFile $file): bool
    {
        $storage = $file->getStorage();
        if (null === $storage) {
            return false;
        }

        $processingFolder = $storage->getProcessingFolder();
        if (null === $processingFolder) {
            return false;
        }

        return $this->pathMatcher->matches($file->getIdentifier(), $processingFolder->getIdentifier());
    }

    private function originalFileIsInExcludedDirectory(FileInterface $file): bool
    {
        $storageBasePath = $file->getStorage()->getConfiguration()['basePath'] ?? '';
        $filePath = rtrim($storageBasePath, '/') . '/' . ltrim($file->getIdentifier(), '/');

        return $this->pathMatcher->matchesAny($filePath, $this->configuration->getExcludeDirectories());
    }

    private function removeProcessedFile(ProcessedFile $processedFile): void
    {
        try {
            $processedFile->delete(true);
        } catch (\Exception $e) {
            $logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
            $logger->error(\sprintf(
                'Failed to remove processed file "%s": %s',
                $processedFile->getIdentifier(),
                $e->getMessage()
            ));
        }
    }
}
