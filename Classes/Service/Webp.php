<?php

declare(strict_types=1);

namespace Plan2net\Webp\Service;

use Doctrine\DBAL\Exception;
use Plan2net\Webp\Converter\Converter;
use Plan2net\Webp\Converter\Exception\ConvertedFileLargerThanOriginalException;
use Plan2net\Webp\Converter\Exception\WillNotRetryWithConfigurationException;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class Webp
{
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

        $originalFilePath = $originalFile->getForLocalProcessing(false);
        if (!@\is_file($originalFilePath)) {
            return;
        }

        $parameters = $this->getParametersForMimeType($originalFile->getMimeType());
        if (empty($parameters)) {
            throw new \InvalidArgumentException(\sprintf('No options given for adapter "%s" and mime type "%s" (file "%s")!', $converterClass, $originalFile->getMimeType(), $originalFile->getIdentifier()));
        }

        if ($this->hasFailedAttempt((int) $originalFile->getUid(), $parameters)) {
            throw new WillNotRetryWithConfigurationException(\sprintf('Conversion for file "%s" failed before! Will not retry with this configuration!', $originalFilePath));
        }

        $targetFilePath = "{$originalFilePath}.webp";

        $converterClass = Configuration::get('converter');

        /** @var Converter $converter */
        $converter = GeneralUtility::makeInstance($converterClass, $parameters);
        $converter->convert($originalFilePath, $targetFilePath);
        $fileSizeTargetFile = @\filesize($targetFilePath);
        if ($fileSizeTargetFile && $originalFile->getSize() <= $fileSizeTargetFile) {
            $this->saveFailedAttempt((int) $originalFile->getUid(), $parameters);
            throw new ConvertedFileLargerThanOriginalException(\sprintf('Converted file (%s) is larger (%d vs. %d) than the original (%s)!', $targetFilePath, $fileSizeTargetFile, $originalFile->getSize(), $originalFilePath));
        }
        $processedFile->updateProperties(
            [
                'width' => $originalFile->getProperty('width'),
                'height' => $originalFile->getProperty('height'),
                'size' => $fileSizeTargetFile,
            ]
        );
    }

    public static function isSupportedMimeType(string $mimeType): bool
    {
        $supportedMimeTypes = (string) Configuration::get('mime_types');
        if (!empty($supportedMimeTypes)) {
            return \in_array(\strtolower($mimeType), \explode(',', \strtolower($supportedMimeTypes)), true);
        }

        return false;
    }

    private function getParametersForMimeType(string $mimeType): ?string
    {
        $parameters = \explode('|', Configuration::get('parameters'));
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

    private function saveFailedAttempt(int $fileId, string $configuration): void
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_webp_failed');
        $queryBuilder->insert('tx_webp_failed')
            ->values([
                'file_id' => $fileId,
                'configuration' => $configuration,
                'configuration_hash' => md5($configuration),
            ])
            ->executeStatement();
    }

    private function hasFailedAttempt(int $fileId, string $configuration): bool
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_webp_failed');

        try {
            return (bool) $queryBuilder->count('uid')
                ->from('tx_webp_failed')
                ->where(
                    $queryBuilder->expr()->eq('file_id', $fileId),
                    $queryBuilder->expr()->eq('configuration_hash',
                        $queryBuilder->createNamedParameter(md5($configuration)))
                )
                ->executeQuery()
                ->fetchOne();
        } catch (Exception $e) {
            return false;
        }
    }
}
