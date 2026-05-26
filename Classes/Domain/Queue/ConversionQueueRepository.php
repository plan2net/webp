<?php

declare(strict_types=1);

namespace Plan2net\Webp\Domain\Queue;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Plan2net\Webp\Format\OutputFormat;
use TYPO3\CMS\Core\Database\ConnectionPool;

final readonly class ConversionQueueRepository
{
    private const TABLE = 'tx_webp_queue';

    public function __construct(
        private ConnectionPool $connectionPool,
    ) {
    }

    public function enqueue(int $originalFileId, int $processedFileId, string $taskType, array $configuration, OutputFormat $format): void
    {
        $serialized = \serialize($configuration);
        $hash = \md5($serialized);
        $now = \time();

        $connection = $this->connectionPool->getConnectionForTable(self::TABLE);
        try {
            $connection->insert(self::TABLE, [
                'original_file_id' => $originalFileId,
                'processed_file_id' => $processedFileId,
                'task_type' => $taskType,
                'configuration' => $serialized,
                'configuration_hash' => $hash,
                'enqueued_at' => $now,
                'format' => $format->value,
            ]);
        } catch (UniqueConstraintViolationException) {
            $connection->update(
                self::TABLE,
                ['enqueued_at' => $now],
                [
                    'original_file_id' => $originalFileId,
                    'processed_file_id' => $processedFileId,
                    'task_type' => $taskType,
                    'configuration_hash' => $hash,
                    'format' => $format->value,
                ]
            );
        }
    }

    /**
     * @return list<ConversionQueueEntry>
     */
    public function fetchBatch(int $limit): array
    {
        if ($limit < 1) {
            throw new \InvalidArgumentException(\sprintf('Batch limit must be >= 1, got %d', $limit));
        }
        $queryBuilder = $this->connectionPool->getConnectionForTable(self::TABLE)->createQueryBuilder();
        $rows = $queryBuilder
            ->select('uid', 'original_file_id', 'processed_file_id', 'task_type', 'configuration', 'configuration_hash', 'enqueued_at', 'format')
            ->from(self::TABLE)
            ->orderBy('enqueued_at', 'ASC')
            ->addOrderBy('uid', 'ASC')
            ->setMaxResults($limit)
            ->executeQuery()
            ->fetchAllAssociative();

        return \array_map(
            static fn (array $row) => new ConversionQueueEntry(
                (int) $row['uid'],
                (int) $row['original_file_id'],
                (int) $row['processed_file_id'],
                (string) $row['task_type'],
                (string) ($row['configuration'] ?? ''),
                (string) $row['configuration_hash'],
                (int) $row['enqueued_at'],
                OutputFormat::tryFrom((string) ($row['format'] ?? 'webp')) ?? OutputFormat::Webp,
            ),
            $rows
        );
    }

    public function remove(int $uid): void
    {
        $this->connectionPool->getConnectionForTable(self::TABLE)->delete(self::TABLE, ['uid' => $uid]);
    }
}
