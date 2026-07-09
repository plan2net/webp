<?php

declare(strict_types=1);

namespace Plan2net\Webp\Domain\Queue;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Plan2net\Webp\Format\OutputFormat;
use TYPO3\CMS\Core\Database\ConnectionPool;

final readonly class ConversionQueueRepository
{
    private const TABLE = 'tx_webp_queue';
    private const DEDUP_COLUMNS = ['original_file_id', 'processed_file_id', 'task_type', 'configuration_hash', 'format'];

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
        $row = [
            'original_file_id' => $originalFileId,
            'processed_file_id' => $processedFileId,
            'task_type' => $taskType,
            'configuration' => $serialized,
            'configuration_hash' => $hash,
            'enqueued_at' => $now,
            'format' => $format->value,
        ];

        if ($connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            $this->upsertPostgreSql($connection, $row);

            return;
        }

        try {
            $connection->insert(self::TABLE, $row);
        } catch (UniqueConstraintViolationException) {
            $connection->update(
                self::TABLE,
                ['enqueued_at' => $row['enqueued_at']],
                $this->dedupCriteria($row)
            );
        }
    }

    /**
     * PostgreSQL logs every failed INSERT before DBAL can catch the unique constraint
     * exception, so duplicate queue entries must be handled with a native upsert.
     *
     * @param array<string, int|string> $row
     */
    private function upsertPostgreSql(Connection $connection, array $row): void
    {
        $platform = $connection->getDatabasePlatform();
        $columns = \array_keys($row);

        $connection->executeStatement(
            \sprintf(
                'INSERT INTO %s (%s) VALUES (%s) ON CONFLICT (%s) DO UPDATE SET %s = EXCLUDED.%s',
                $platform->quoteSingleIdentifier(self::TABLE),
                \implode(', ', \array_map($platform->quoteSingleIdentifier(...), $columns)),
                \implode(', ', \array_fill(0, \count($columns), '?')),
                \implode(', ', \array_map($platform->quoteSingleIdentifier(...), self::DEDUP_COLUMNS)),
                $platform->quoteSingleIdentifier('enqueued_at'),
                $platform->quoteSingleIdentifier('enqueued_at'),
            ),
            \array_values($row),
        );
    }

    /**
     * @param array<string, int|string> $row
     *
     * @return array<string, int|string>
     */
    private function dedupCriteria(array $row): array
    {
        return \array_intersect_key($row, \array_flip(self::DEDUP_COLUMNS));
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
