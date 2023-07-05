<?php

declare(strict_types=1);

namespace Plan2net\Webp\EventListener;

use Plan2net\Webp\Converter\Exception\ConvertedFileLargerThanOriginalException;
use Plan2net\Webp\Converter\Exception\WillNotRetryWithConfigurationException;
use Plan2net\Webp\Service\Configuration;
use Plan2net\Webp\Service\Webp as WebpService;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Resource\Event\AfterFileProcessingEvent;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Resource\ProcessedFileRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class AfterFileProcessing
{
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
        array $configuration
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

            /** @var ProcessedFileRepository $processedFileRepository */
            $processedFileRepository = GeneralUtility::makeInstance(ProcessedFileRepository::class);
            // This will either return an existing file or create a new one
            $processedFileWebp = $processedFileRepository->findOneByOriginalFileAndTaskTypeAndConfiguration(
                $file,
                $taskType,
                $configuration + [
                    'webp' => true,
                ]
            );
            // Check if reprocessing is required
            if (!$this->needsReprocessing($processedFileWebp)) {
                return;
            }

            $logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
            try {
                /** @var WebpService $service */
                $service = GeneralUtility::makeInstance(WebpService::class);
                $service->process($processedFile, $processedFileWebp);

                // This will add or update
                $processedFileRepository->add($processedFileWebp);
            } catch (WillNotRetryWithConfigurationException $e) {
                $logger->notice($e->getMessage());
            } catch (ConvertedFileLargerThanOriginalException $e) {
                $logger->warning($e->getMessage());
                $this->removeProcessedFile($processedFileWebp);
            } catch (\Exception $e) {
                $logger->error(
                    \sprintf(
                        'Failed to convert image "%s" to webp with: %s',
                        $processedFile->getIdentifier(),
                        $e->getMessage()
                    )
                );
                $this->removeProcessedFile($processedFileWebp);
            }
        }
    }

    private function shouldProcess(string $taskType, ProcessedFile $processedFile): bool
    {
        if ('Image.CropScaleMask' !== $taskType) {
            return false;
        }

        if (!WebpService::isSupportedMimeType($processedFile->getOriginalFile()->getMimeType())) {
            return false;
        }

        // Convert images in any folder or only in the _processed_ folder
        $convertAllImages = (bool) Configuration::get('convert_all');
        if (!$convertAllImages && !$this->isFileInProcessingFolder($processedFile)) {
            return false;
        }

        // Process files only in a local and writable storage
        if (!$this->isStorageLocalAndWritable($processedFile)) {
            return false;
        }

        if ($this->originalFileIsInExcludedDirectory($processedFile->getOriginalFile())) {
            return false;
        }

        return true;
    }

    private function needsReprocessing(ProcessedFile $processedFile): bool
    {
        return $processedFile->isNew()
            || (!$processedFile->usesOriginalFile() && !$processedFile->exists())
            || $processedFile->isOutdated();
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

        return str_starts_with($file->getIdentifier(), $processingFolder->getIdentifier());
    }

    private function isStorageLocalAndWritable(ProcessedFile $file): bool
    {
        $storage = $file->getStorage();

        // Ignore files in fallback storage (e.g. files from extensions)
        if (null === $storage || 0 === $storage->getStorageRecord()['uid']) {
            return false;
        }

        return 'Local' === $storage->getDriverType() && $storage->isWritable();
    }

    private function originalFileIsInExcludedDirectory(FileInterface $file): bool
    {
        $storageBasePath = $file->getStorage()->getConfiguration()['basePath'];
        $filePath = rtrim($storageBasePath, '/') . '/' . ltrim($file->getIdentifier(), '/');
        $excludeDirectories = array_filter(explode(';', (string) Configuration::get('exclude_directories')));

        if (!empty($excludeDirectories)) {
            foreach ($excludeDirectories as $excludedDirectory) {
                if (str_starts_with($filePath, trim($excludedDirectory))) {
                    return true;
                }
            }
        }

        return false;
    }

    private function removeProcessedFile(ProcessedFile $processedFile): void
    {
        try {
            $processedFile->delete(true);
        } catch (\Exception $e) {
            $logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
            $logger->error(\sprintf('Failed to remove processed file "%s": %s',
                $processedFile->getIdentifier(),
                $e->getMessage()
            ));
        }
    }
}
