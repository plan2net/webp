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
    /**
     * Perform image conversion
     *
     * @param FileInterface $originalFile
     * @param ProcessedFile $processedFile
     * @throws ConvertedFileLargerThanOriginalException
     * @throws WillNotRetryWithConfigurationException
     */
    public function process(FileInterface $originalFile, ProcessedFile $processedFile): void
    {
        $processedFile->setName($originalFile->getName() . '.webp');
        $processedFile->setIdentifier($originalFile->getIdentifier() . '.webp');

        $originalFilePath = $originalFile->getForLocalProcessing(false);
        $targetFilePath = "$originalFilePath.webp";

        $converterClass = Configuration::get('converter');
        $parameters = $this->getParametersForMimeType($originalFile->getMimeType());
        if (!empty($parameters)) {
            if ($this->hasFailedAttempt((int)$originalFile->getUid(), $parameters)) {
                throw new WillNotRetryWithConfigurationException(
                    sprintf('Converted file (%s) is larger than the original (%s)! Will not retry with this configuration!',
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
                    sprintf('Converted file (%s) is larger than the original (%s)! Will not retry with this configuration!',
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
        } else {
            throw new InvalidArgumentException(sprintf('No options given for adapter "%s"!', $converterClass));
        }
    }

    /**
     * @param $mimeType
     * @return bool
     */
    public static function isSupportedMimeType(string $mimeType): bool
    {
        $supportedMimeTypes = (string)Configuration::get('mime_types');
        if (!empty($supportedMimeTypes)) {
            return in_array(strtolower($mimeType), explode(',', strtolower($supportedMimeTypes)), true);
        }

        return false;
    }

    /**
     * @param string $mimeType
     * @return string|null
     */
    protected function getParametersForMimeType(string $mimeType): ?string
    {
        $parameters = explode('|', Configuration::get('parameters'));
        foreach ($parameters as $parameter) {
            [$type, $options] = explode('::', $parameter, 2);
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

    /**
     * @param int $fileId
     * @param string $configuration
     */
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

    /**
     * @param int $fileId
     * @param string $configuration
     * @return bool
     */
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
