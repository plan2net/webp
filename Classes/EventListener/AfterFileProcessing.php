<?php

declare(strict_types=1);

namespace Plan2net\Webp\EventListener;

use Exception;
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
use function implode;
use function serialize;
use function sprintf;
use function strpos;

/**
 * Class AfterFileProcessing
 *
 * @author Wolfgang Klinger <wk@plan2.net>
 */
class AfterFileProcessing
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
     * Process a file using the configured adapter to create a webp copy
     *
     * @param FileInterface|File $file
     */
    protected function processFile(
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
                    'webp' => true
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

                // Be aware that using shortMD5 results in a very bad checksum,
                // but TYPO3 CMS core has a limit on this field
                $processedFileWebp->updateProperties(
                    [
                        'checksum' => substr(
                            md5(
                                implode(
                                    '|',
                                    $this->getChecksumData($file, $processedFileWebp, $configuration)
                                )
                            ),
                            0,
                            10
                        );
                    ]
                );

                // This will add or update
                $processedFileRepository->add($processedFileWebp);
            } catch (WillNotRetryWithConfigurationException $e) {
                // silently ignore
            } catch (ConvertedFileLargerThanOriginalException $e) {
                $logger->warning($e->getMessage());
                $this->removeProcessedFile($processedFileWebp);
            } catch (Exception $e) {
                $logger->error(sprintf('Failed to convert image to webp: %s', $e->getMessage()));
                $this->removeProcessedFile($processedFileWebp);
            }
        }
    }

    protected function shouldProcess(string $taskType, ProcessedFile $processedFile): bool
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

        return true;
    }

    protected function needsReprocessing(ProcessedFile $processedFile): bool
    {
        return $processedFile->isNew() ||
            (!$processedFile->usesOriginalFile() && !$processedFile->exists()) ||
            $processedFile->isOutdated();
    }

    protected function isFileInProcessingFolder(ProcessedFile $file): bool
    {
        $processingFolder = $file->getStorage()->getProcessingFolder();

        return 0 === strpos($file->getIdentifier(), $processingFolder->getIdentifier());
    }

    protected function isStorageLocalAndWritable(ProcessedFile $file): bool
    {
        $storage = $file->getStorage();
        // Ignore files in fallback storage (e.g. files from extensions)
        if (0 === $storage->getStorageRecord()['uid']) {
            return false;
        }

        return 'Local' === $storage->getDriverType() && $storage->isWritable();
    }

    /**
     * @param FileInterface|File $file
     */
    protected function getChecksumData(FileInterface $file, ProcessedFile $processedFile, array $configuration): array
    {
        return [
            $file->getUid(),
            'Image.Webp' . '.' . $processedFile->getName() . $file->getModificationTime(),
            serialize($configuration)
        ];
    }

    protected function removeProcessedFile(ProcessedFile $processedFile): void
    {
        try {
            $processedFile->delete(true);
        } catch (Exception $e) {
            $logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
            $logger->error(sprintf('Failed to remove processed file "%s": %s',
                $processedFile->getIdentifier(),
                $e->getMessage()
            ));
        }
    }
}
