<?php

declare(strict_types=1);

namespace Plan2net\Webp\Tests\Functional\Domain\Queue;

use PHPUnit\Framework\Attributes\Test;
use Plan2net\Webp\Domain\Queue\ConversionQueueRepository;
use Plan2net\Webp\Format\OutputFormat;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class ConversionQueueRepositoryTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = [
        'install',
        'scheduler',
    ];

    protected array $testExtensionsToLoad = [
        'plan2net/webp',
    ];

    #[Test]
    public function enqueueInsertsRow(): void
    {
        $repository = $this->get(ConversionQueueRepository::class);
        $repository->enqueue(42, 0, 'Image.CropScaleMask', ['width' => 100]);

        self::assertSame(1, $this->countRows());
    }

    #[Test]
    public function enqueueIsIdempotentForSameTuple(): void
    {
        $repository = $this->get(ConversionQueueRepository::class);
        $repository->enqueue(42, 7, 'Image.CropScaleMask', ['width' => 100]);
        $repository->enqueue(42, 7, 'Image.CropScaleMask', ['width' => 100]);

        self::assertSame(1, $this->countRows());
    }

    #[Test]
    public function enqueueAllowsDifferentProcessedFilesForSameOriginal(): void
    {
        $repository = $this->get(ConversionQueueRepository::class);
        $repository->enqueue(42, 7, 'Image.CropScaleMask', ['width' => 100]);
        $repository->enqueue(42, 8, 'Image.CropScaleMask', ['width' => 200]);

        self::assertSame(2, $this->countRows());
    }

    #[Test]
    public function enqueueRefreshesTimestampOnDuplicate(): void
    {
        $repository = $this->get(ConversionQueueRepository::class);
        $repository->enqueue(42, 0, 'Image.CropScaleMask', ['width' => 100]);
        $oldTimestamp = $this->fetchEnqueuedAt(42);

        \sleep(1);
        $repository->enqueue(42, 0, 'Image.CropScaleMask', ['width' => 100]);
        $newTimestamp = $this->fetchEnqueuedAt(42);

        self::assertGreaterThan($oldTimestamp, $newTimestamp);
    }

    #[Test]
    public function fetchBatchReturnsOldestFirst(): void
    {
        $repository = $this->get(ConversionQueueRepository::class);
        $repository->enqueue(2, 0, 'Image.CropScaleMask', ['n' => 'second']);
        \sleep(1);
        $repository->enqueue(1, 0, 'Image.CropScaleMask', ['n' => 'first-but-later']);

        $batch = $repository->fetchBatch(10);

        self::assertCount(2, $batch);
        self::assertSame(2, $batch[0]->originalFileId);
        self::assertSame(1, $batch[1]->originalFileId);
    }

    #[Test]
    public function fetchBatchRespectsLimit(): void
    {
        $repository = $this->get(ConversionQueueRepository::class);
        for ($i = 1; $i <= 5; ++$i) {
            $repository->enqueue($i, 0, 'Image.CropScaleMask', ['i' => $i]);
        }

        $batch = $repository->fetchBatch(3);

        self::assertCount(3, $batch);
    }

    #[Test]
    public function fetchBatchExposesAllTupleFields(): void
    {
        $repository = $this->get(ConversionQueueRepository::class);
        $repository->enqueue(42, 7, 'Image.CropScaleMask', ['width' => 100]);

        $entry = $repository->fetchBatch(1)[0];

        self::assertSame(42, $entry->originalFileId);
        self::assertSame(7, $entry->processedFileId);
        self::assertSame('Image.CropScaleMask', $entry->taskType);
        self::assertSame(['width' => 100], \unserialize($entry->configuration, ['allowed_classes' => false]));
    }

    #[Test]
    public function enqueueAllowsDifferentTaskTypesForSameTuple(): void
    {
        $repository = $this->get(ConversionQueueRepository::class);
        $repository->enqueue(42, 7, 'Image.CropScaleMask', ['width' => 100]);
        $repository->enqueue(42, 7, 'Image.Preview', ['width' => 100]);

        self::assertSame(2, $this->countRows());
    }

    #[Test]
    public function fetchBatchRejectsLimitBelowOne(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->get(ConversionQueueRepository::class)->fetchBatch(0);
    }

    #[Test]
    public function enqueueStoresFormatColumn(): void
    {
        $repository = $this->get(ConversionQueueRepository::class);
        $repository->enqueue(1, 0, 'Image.CropScaleMask', ['width' => 32], OutputFormat::Avif);

        $row = $this->getConnectionPool()
            ->getConnectionForTable('tx_webp_queue')
            ->select(['format'], 'tx_webp_queue', ['original_file_id' => 1])
            ->fetchAssociative();
        self::assertNotFalse($row);
        self::assertSame('avif', $row['format']);
    }

    #[Test]
    public function dedupAcrossFormatsAllowsParallelEntries(): void
    {
        $repository = $this->get(ConversionQueueRepository::class);
        $repository->enqueue(1, 0, 'Image.CropScaleMask', ['width' => 32], OutputFormat::Webp);
        $repository->enqueue(1, 0, 'Image.CropScaleMask', ['width' => 32], OutputFormat::Avif);

        self::assertSame(2, $this->countRows());
    }

    #[Test]
    public function fetchBatchExposesFormat(): void
    {
        $repository = $this->get(ConversionQueueRepository::class);
        $repository->enqueue(1, 0, 'Image.CropScaleMask', [], OutputFormat::Jxl);

        $entry = $repository->fetchBatch(1)[0];
        self::assertSame(OutputFormat::Jxl, $entry->format);
    }

    #[Test]
    public function removeRemovesByUid(): void
    {
        $repository = $this->get(ConversionQueueRepository::class);
        $repository->enqueue(1, 0, 'Image.CropScaleMask', []);
        $repository->enqueue(2, 0, 'Image.CropScaleMask', []);

        $batch = $repository->fetchBatch(10);
        $repository->remove($batch[0]->uid);

        self::assertSame(1, $this->countRows());
    }

    private function countRows(): int
    {
        return (int) $this->getConnectionPool()
            ->getConnectionForTable('tx_webp_queue')
            ->count('uid', 'tx_webp_queue', []);
    }

    private function fetchEnqueuedAt(int $originalFileId): int
    {
        $row = $this->getConnectionPool()
            ->getConnectionForTable('tx_webp_queue')
            ->select(['enqueued_at'], 'tx_webp_queue', ['original_file_id' => $originalFileId])
            ->fetchAssociative();

        return false !== $row ? (int) $row['enqueued_at'] : 0;
    }
}
