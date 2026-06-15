<?php

declare(strict_types=1);

namespace Plan2net\Webp\Backend;

use Plan2net\Webp\Service\CompressionReport;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Resource\StorageRepository;

final readonly class CompressionReportProvider
{
    private const PROCESSED_TABLE = 'sys_file_processedfile';
    private const FILE_TABLE = 'sys_file';

    public function __construct(
        private ConnectionPool $connectionPool,
        private StorageRepository $storageRepository,
    ) {
    }

    /**
     * @return list<array{label: string, sourceSize: int, results: array<string, array{size: int, savingsPercent: int}>}>
     */
    public function forFile(int $fileUid): array
    {
        if ($fileUid <= 0) {
            return [];
        }

        $processedRows = $this->connectionPool
            ->getConnectionForTable(self::PROCESSED_TABLE)
            ->select(['storage', 'identifier', 'configuration', 'width', 'height'], self::PROCESSED_TABLE, ['original' => $fileUid])
            ->fetchAllAssociative();

        $rows = [];
        foreach ($processedRows as $row) {
            $size = $this->resolveSize((int) $row['storage'], (string) $row['identifier']);
            if (null === $size) {
                continue;
            }

            $configuration = '' !== (string) ($row['configuration'] ?? '')
                ? @\unserialize((string) $row['configuration'], ['allowed_classes' => false])
                : [];
            $format = \is_array($configuration) && isset($configuration['format']) && \is_string($configuration['format'])
                ? $configuration['format']
                : null;

            $rows[] = [
                'identifier' => (string) $row['identifier'],
                'size' => $size,
                'width' => (int) $row['width'],
                'height' => (int) $row['height'],
                'format' => $format,
            ];
        }

        $file = $this->connectionPool
            ->getConnectionForTable(self::FILE_TABLE)
            ->select(['identifier', 'size'], self::FILE_TABLE, ['uid' => $fileUid])
            ->fetchAssociative();

        $originalIdentifier = \is_array($file) ? (string) ($file['identifier'] ?? '') : '';
        $originalSize = \is_array($file) ? (int) ($file['size'] ?? 0) : 0;

        return CompressionReport::build($rows, $originalSize, $originalIdentifier);
    }

    private function resolveSize(int $storageUid, string $identifier): ?int
    {
        if ($storageUid <= 0 || '' === $identifier) {
            return null;
        }

        $storage = $this->storageRepository->findByUid($storageUid);
        if (!$storage instanceof ResourceStorage) {
            return null;
        }

        try {
            if (!$storage->hasFile($identifier)) {
                return null;
            }
            $info = $storage->getFileInfoByIdentifier($identifier, ['size']);
        } catch (\Throwable) {
            return null;
        }

        return isset($info['size']) ? (int) $info['size'] : null;
    }
}
