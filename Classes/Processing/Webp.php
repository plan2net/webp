<?php
declare(strict_types=1);

namespace Plan2net\Webp\Processing;

use TYPO3\CMS\Core\Resource\Driver\DriverInterface;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Resource\ProcessedFileRepository;
use TYPO3\CMS\Core\Resource\Service\FileProcessingService;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class Webp
 *
 * @package Plan2net\Webp\Processing
 * @author  Wolfgang Klinger <wk@plan2.net>
 */
class Webp
{

    /**
     * Process file
     * Create webp image copy
     *
     * @param FileProcessingService $fileProcessingService
     * @param DriverInterface       $driver
     * @param ProcessedFile         $processedFile
     * @param File                  $file
     * @param string                $taskType
     * @param array                 $configuration
     */
    public function processFile(
        FileProcessingService $fileProcessingService,
        DriverInterface $driver,
        ProcessedFile $processedFile,
        File $file,
        $taskType,
        array $configuration
    ) {
        if (!$this->isInProcessingFolder($processedFile)) {
            return;
        }
        if (!$this->isSupportedFileExtension($processedFile->getExtension())) {
            return;
        }
        if ($taskType !== 'Image.CropScaleMask') {
            return;
        }
        /** @var ProcessedFileRepository $processedFileRepository */
        $processedFileRepository = GeneralUtility::makeInstance(ProcessedFileRepository::class);
        $configuration['webp'] = true;
        $processedFileWebp = $processedFileRepository->findOneByOriginalFileAndTaskTypeAndConfiguration($file, $taskType, $configuration);
        // Check if processing is required
        if (!$this->needsReprocessing($processedFileWebp)) {
            return;
        }
        /** @var \Plan2net\Webp\Service\Image\Webp $service */
        $service = GeneralUtility::makeInstance(\Plan2net\Webp\Service\Image\Webp::class);
        $service->process($processedFile, $processedFileWebp);

        $processedFileWebp->updateProperties(
            [
                'checksum' => GeneralUtility::shortMD5(implode('|', $this->getChecksumData($file, $processedFileWebp, $configuration)))
            ]
        );

        // add will add or update
        $processedFileRepository->add($processedFileWebp);
    }

    /**
     * @param ProcessedFile $processedFile
     * @return bool
     */
    protected function needsReprocessing($processedFile): bool
    {
        return $processedFile->isNew() ||
            (!$processedFile->usesOriginalFile() && !$processedFile->exists()) ||
            $processedFile->isOutdated();
    }

    /**
     * @param ProcessedFile $file
     * @return bool
     */
    protected function isInProcessingFolder($file): bool
    {
        $processingFolder = $file->getStorage()->getProcessingFolder();
        if (strpos($file->getIdentifier(), $processingFolder->getIdentifier()) !== false) {
            return true;
        }

        return false;
    }

    /**
     * @param string $extension
     * @return bool
     */
    protected function isSupportedFileExtension($extension): bool
    {
        if (array_search(strtolower($extension), ['jpg', 'jpeg', 'png']) !== false) {
            return true;
        }

        return false;
    }

    /**
     * @param \TYPO3\CMS\Core\Resource\File $file
     * @param \TYPO3\CMS\Core\Resource\ProcessedFile $processedFile
     * @param array $configuration
     * @return array
     */
    protected function getChecksumData($file, $processedFile, $configuration): array
    {
        return [
            $file->getUid(),
            'Image.Webp' . '.' . $processedFile->getName() . $file->getModificationTime(),
            serialize($configuration)
        ];
    }

}
