<?php

declare(strict_types=1);

namespace Plan2net\Webp\Service;

use Plan2net\Webp\Converter\Converter;
use Plan2net\Webp\Converter\Exception\ConvertedFileLargerThanOriginalException;
use Plan2net\Webp\Converter\Exception\WillNotRetryWithConfigurationException;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class Webp
{
    public function __construct(
        private readonly Configuration $configuration,
        private readonly FailedAttemptsRepository $failedAttempts,
    ) {
    }

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

        $targetFilePath = "{$originalFilePath}.webp";
        $mimeType = $originalFile->getMimeType();
        $parameters = $this->getParametersForMimeType($mimeType);

        $fileUid = (int) $originalFile->getUid();
        if (null !== $parameters && $this->failedAttempts->wasAttempted($fileUid, $parameters)) {
            throw new WillNotRetryWithConfigurationException(\sprintf('Conversion for file "%s" failed before! Will not retry with this configuration!', $originalFilePath));
        }

        try {
            $fileSizeTargetFile = $this->convertFilePath($originalFilePath, $targetFilePath, $mimeType);
        } catch (ConvertedFileLargerThanOriginalException $e) {
            if (null !== $parameters) {
                $this->failedAttempts->record($fileUid, $parameters);
            }
            throw $e;
        }

        $processedFile->updateProperties([
            'width' => $originalFile->getProperty('width'),
            'height' => $originalFile->getProperty('height'),
            'size' => $fileSizeTargetFile,
        ]);
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
     * @throws ConvertedFileLargerThanOriginalException if the WebP output is not smaller than the source — the oversize target is removed before the exception is thrown
     */
    public function convertFilePath(string $sourcePath, string $targetPath, string $mimeType): int
    {
        $converterClass = $this->configuration->getConverter();
        if (empty($converterClass)) {
            throw new \RuntimeException('No WebP converter configured. Please check extension configuration.');
        }

        $parameters = $this->getParametersForMimeType($mimeType);
        if (empty($parameters)) {
            throw new \InvalidArgumentException(\sprintf('No options given for adapter "%s" and mime type "%s" (file "%s")!', $converterClass, $mimeType, $sourcePath));
        }

        /** @var Converter $converter */
        $converter = GeneralUtility::makeInstance($converterClass, $parameters, $this->configuration);
        $converter->convert($sourcePath, $targetPath);

        $sourceSize = (int) @\filesize($sourcePath);
        $targetSize = (int) @\filesize($targetPath);
        if ($targetSize > 0 && $sourceSize > 0 && $sourceSize <= $targetSize) {
            @\unlink($targetPath);
            throw new ConvertedFileLargerThanOriginalException(\sprintf('Converted file (%s) is larger (%d vs. %d) than the original (%s)!', $targetPath, $targetSize, $sourceSize, $sourcePath));
        }

        return $targetSize;
    }

    private function getParametersForMimeType(string $mimeType): ?string
    {
        $parameters = \explode('|', $this->configuration->getParameters());
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
}
