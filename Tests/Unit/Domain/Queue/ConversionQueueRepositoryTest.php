<?php

declare(strict_types=1);

namespace Plan2net\Webp\Tests\Unit\Domain\Queue;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Plan2net\Webp\Domain\Queue\ConversionQueueRepository;
use Plan2net\Webp\Format\OutputFormat;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;

final class ConversionQueueRepositoryTest extends TestCase
{
    #[Test]
    public function enqueueOnPostgreSqlEmitsNativeUpsertInsteadOfInsert(): void
    {
        $capturedSql = null;
        $capturedParameters = null;

        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn(new PostgreSQLPlatform());
        $connection->expects(self::never())->method('insert');
        $connection->expects(self::once())
            ->method('executeStatement')
            ->willReturnCallback(static function (string $sql, array $parameters) use (&$capturedSql, &$capturedParameters): int {
                $capturedSql = $sql;
                $capturedParameters = $parameters;

                return 1;
            });

        $this->repositoryFor($connection)->enqueue(42, 7, 'Image.CropScaleMask', ['width' => 100], OutputFormat::Webp);

        $expectedSql = 'INSERT INTO "tx_webp_queue" '
            . '("original_file_id", "processed_file_id", "task_type", "configuration", "configuration_hash", "enqueued_at", "format") '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?) '
            . 'ON CONFLICT ("original_file_id", "processed_file_id", "task_type", "configuration_hash", "format") '
            . 'DO UPDATE SET "enqueued_at" = EXCLUDED."enqueued_at"';
        self::assertSame($expectedSql, $capturedSql);

        $serialized = \serialize(['width' => 100]);
        self::assertSame(42, $capturedParameters[0]);
        self::assertSame(7, $capturedParameters[1]);
        self::assertSame('Image.CropScaleMask', $capturedParameters[2]);
        self::assertSame($serialized, $capturedParameters[3]);
        self::assertSame(\md5($serialized), $capturedParameters[4]);
        self::assertIsInt($capturedParameters[5]);
        self::assertSame('webp', $capturedParameters[6]);
    }

    #[Test]
    public function enqueueOnNonPostgreSqlPlatformUsesInsert(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn($this->createMock(AbstractPlatform::class));
        $connection->expects(self::never())->method('executeStatement');
        $connection->expects(self::once())
            ->method('insert')
            ->with(
                'tx_webp_queue',
                self::callback(static function (array $row): bool {
                    return 42 === $row['original_file_id']
                        && 7 === $row['processed_file_id']
                        && 'Image.CropScaleMask' === $row['task_type']
                        && 'webp' === $row['format'];
                })
            );

        $this->repositoryFor($connection)->enqueue(42, 7, 'Image.CropScaleMask', ['width' => 100], OutputFormat::Webp);
    }

    private function repositoryFor(Connection $connection): ConversionQueueRepository
    {
        $connectionPool = $this->createMock(ConnectionPool::class);
        $connectionPool->method('getConnectionForTable')->with('tx_webp_queue')->willReturn($connection);

        return new ConversionQueueRepository($connectionPool);
    }
}
