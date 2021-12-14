<?php
declare(strict_types=1);

namespace Plan2net\Webp\Service;

use InvalidArgumentException;
use Plan2net\Webp\Converter\ConvertedFileLargerThanOriginalException;
use Plan2net\Webp\Converter\Converter;
use Plan2net\Webp\Converter\WillNotRetryWithConfigurationException;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use function strtolower;

/**
 * Class Webp
 *
 * @package Plan2net\Webp\Service
 * @author  Wolfgang Klinger <wk@plan2.net>
 */
class Webp
{
    protected const FILE_EXTENSION = '.webp';

    /**
     * Perform image conversion
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

        $converterClass = (string)Configuration::get('converter');
        $parameters = Configuration::getParametersForMimeType($originalFile->getMimeType());
        if (empty($parameters)) {
            throw new InvalidArgumentException(sprintf('No options given for adapter "%s"!', $converterClass));
        }

        if ($this->hasFailedAttempt((int)$originalFile->getUid(), $parameters)) {
            throw new WillNotRetryWithConfigurationException(
                sprintf('Failed before; Will not retry with this configuration!',
                    $targetFilePath, $originalFilePath)
            );
        }

        /** @var Converter $converter */
        $converter = GeneralUtility::makeInstance($converterClass, $parameters);
        $converter->convert($originalFilePath, $targetFilePath);
        $fileSizeTargetFile = @filesize($targetFilePath);
        if ($originalFile->getSize() <= $fileSizeTargetFile) {
            $this->saveFailedAttempt((int)$originalFile->getUid(), $parameters);
            throw new ConvertedFileLargerThanOriginalException(
                sprintf('Converted file (%s) is larger than the original (%s)!',
                    $targetFilePath, $originalFilePath)
            );
        }
        $processedFile->updateProperties(
            [
                'width' => $originalFile->getProperty('width'),
                'height' => $originalFile->getProperty('height'),
                'size' => $fileSizeTargetFile
            ]
        );
    }

    protected function saveFailedAttempt(int $fileId, string $configuration): void
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_webp_failed');
        $queryBuilder->insert('tx_webp_failed')
            ->values([
                'file_id' => $fileId,
                'configuration' => $configuration,
                'configuration_hash' => sha1($configuration)
            ])
            ->execute();
    }

    protected function hasFailedAttempt(int $fileId, string $configuration): bool
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_webp_failed');

        return (bool)$queryBuilder->count('uid')
            ->from('tx_webp_failed')
            ->where(
                $queryBuilder->expr()->eq('file_id', $fileId),
                $queryBuilder->expr()->eq('configuration_hash',
                    $queryBuilder->createNamedParameter(sha1($configuration)))
            )
            ->execute()
            ->fetchColumn();
    }
}
