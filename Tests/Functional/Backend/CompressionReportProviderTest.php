<?php

declare(strict_types=1);

namespace Plan2net\Webp\Tests\Functional\Backend;

use PHPUnit\Framework\Attributes\Test;
use Plan2net\Webp\Backend\CompressionReportProvider;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class CompressionReportProviderTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = ['install', 'scheduler'];
    protected array $testExtensionsToLoad = ['plan2net/webp'];

    #[Test]
    public function buildsReportFromGeneratedFilesForTheFile(): void
    {
        $this->get(StorageRepository::class)->findAll();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/Database/sys_file.csv');

        $dir = $this->instancePath . '/fileadmin/_processed_/x/';
        \mkdir($dir, 0o777, true);
        \file_put_contents($dir . 'csm_tiny.png', \str_repeat('a', 1000));
        \file_put_contents($dir . 'csm_tiny.png.webp', \str_repeat('b', 400));

        $connection = $this->getConnectionPool()->getConnectionForTable('sys_file_processedfile');
        $connection->insert('sys_file_processedfile', [
            'storage' => 1,
            'original' => 1,
            'identifier' => '/_processed_/x/csm_tiny.png',
            'configuration' => \serialize(['width' => 16, 'height' => 16]),
            'width' => 16,
            'height' => 16,
        ]);
        $connection->insert('sys_file_processedfile', [
            'storage' => 1,
            'original' => 1,
            'identifier' => '/_processed_/x/csm_tiny.png.webp',
            'configuration' => \serialize(['width' => 16, 'height' => 16, 'format' => 'webp', 'webp' => true]),
            'width' => 16,
            'height' => 16,
        ]);

        $provider = new CompressionReportProvider($this->getConnectionPool(), $this->get(StorageRepository::class));
        $report = $provider->forFile(1);

        self::assertCount(1, $report);
        self::assertSame('16×16', $report[0]['label']);
        self::assertSame(1000, $report[0]['sourceSize']);
        self::assertSame(400, $report[0]['results']['webp']['size']);
        self::assertSame(60, $report[0]['results']['webp']['savingsPercent']);
    }

    #[Test]
    public function returnsEmptyReportWhenNoSiblingsExist(): void
    {
        $this->get(StorageRepository::class)->findAll();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/Database/sys_file.csv');

        $provider = new CompressionReportProvider($this->getConnectionPool(), $this->get(StorageRepository::class));
        self::assertSame([], $provider->forFile(1));
    }

    #[Test]
    public function siblingWhoseFileIsMissingOnDiskIsSkipped(): void
    {
        $this->get(StorageRepository::class)->findAll();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/Database/sys_file.csv');

        // Row points at a sibling identifier with no actual file on disk.
        $this->getConnectionPool()->getConnectionForTable('sys_file_processedfile')->insert('sys_file_processedfile', [
            'storage' => 1,
            'original' => 1,
            'identifier' => '/_processed_/x/csm_missing.png.webp',
            'configuration' => \serialize(['width' => 16, 'height' => 16, 'format' => 'webp', 'webp' => true]),
            'width' => 16,
            'height' => 16,
        ]);

        $provider = new CompressionReportProvider($this->getConnectionPool(), $this->get(StorageRepository::class));

        self::assertSame([], $provider->forFile(1));
    }
}
