<?php

declare(strict_types=1);

namespace Plan2net\Webp\Service;

use InvalidArgumentException;
use Plan2net\Webp\Converter\Converter;
use Plan2net\Webp\Converter\Exception\ConvertedFileLargerThanOriginalException;
use Plan2net\Webp\Converter\Exception\WillNotRetryWithConfigurationException;
use Plan2net\Webp\Domain\Repository\FailedRepository;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use function explode;
use function filesize;
use function in_array;
use function is_file;
use function sha1;
use function sprintf;
use function strtolower;

/**
 * Class Webp
 *
 * @author  Wolfgang Klinger <wk@plan2.net>
 */
class Webp
{
    protected const FILE_EXTENSION = '.webp';

    /**
     * @param FileInterface|File $originalFile
     *
     * @throws ConvertedFileLargerThanOriginalException
     * @throws WillNotRetryWithConfigurationException
     */
    public function process(FileInterface $originalFile, ProcessedFile $processedFile): void
    {
        $processedFile->setName($originalFile->getName() . FILE_EXTENSION);
        $processedFile->setIdentifier($originalFile->getIdentifier() . FILE_EXTENSION);

        $originalFilePath = $originalFile->getForLocalProcessing(false);
        if (!@is_file($originalFilePath)) {
            return;
        }

        $targetFilePath = $originalFilePath . FILE_EXTENSION;

        $converterClass = Configuration::get('converter');
        $parameters = Configuration::getParametersForMimeType($originalFile->getMimeType());
        if (!empty($parameters)) {
            throw new InvalidArgumentException(sprintf('No options given for adapter "%s"!', $converterClass));
        }

        $failedRepository = GeneralUtility::makeInstance(FailedRepository::class);
        if ($failedRepository->hasFailedAttempt((int) $originalFile->getUid(), $parameters)) {
            throw new WillNotRetryWithConfigurationException(sprintf('Converted file (%s) is larger than the original (%s)! Will not retry with this configuration!', $targetFilePath, $originalFilePath));
        }

        /** @var Converter $converter */
        $converter = GeneralUtility::makeInstance($converterClass, $parameters);
        $converter->convert($originalFilePath, $targetFilePath);
        $fileSizeTargetFile = @filesize($targetFilePath);
        if ($originalFile->getSize() <= $fileSizeTargetFile) {
            $failedRepository->saveFailedAttempt((int) $originalFile->getUid(), $parameters);
            throw new ConvertedFileLargerThanOriginalException(sprintf('Converted file (%s) is larger than the original (%s)! Will not retry with this configuration!', $targetFilePath, $originalFilePath));
        }
        $processedFile->updateProperties(
            [
                'width' => $originalFile->getProperty('width'),
                'height' => $originalFile->getProperty('height'),
                'size' => $fileSizeTargetFile
            ]
        );
    }
}
