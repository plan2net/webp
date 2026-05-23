<?php

declare(strict_types=1);

namespace Plan2net\Webp\Tests\Functional\Command;

use PHPUnit\Framework\Attributes\Test;
use Plan2net\Webp\Command\ProcessConversionQueueCommand;
use Plan2net\Webp\Domain\Queue\ConversionQueueRepository;
use Plan2net\Webp\Tests\Functional\Fixtures\Doubles\DeterministicWebpConverter;
use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class ProcessConversionQueueCommandTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = [
        'install',
        'scheduler',
    ];

    protected array $testExtensionsToLoad = [
        'plan2net/webp',
    ];

    private string $fileadminPath;

    #[Test]
    public function queueModeProcessesEnqueuedEntries(): void
    {
        $file = $this->getFile(1);
        $this->get(ConversionQueueRepository::class)->enqueue(
            (int) $file->getUid(),
            0,
            'Image.CropScaleMask',
            ['webp' => true]
        );

        $exitCode = $this->runCommand([]);

        self::assertSame(0, $exitCode);
        self::assertSame(0, $this->countQueueRows());
        self::assertFileExists($this->fileadminPath . 'tiny.png.webp');
    }

    #[Test]
    public function queueModeSkipsCurrentWebp(): void
    {
        $file = $this->getFile(1);

        // First drain creates the webp.
        $this->get(ConversionQueueRepository::class)->enqueue(
            (int) $file->getUid(),
            0,
            'Image.CropScaleMask',
            ['webp' => true]
        );
        $this->runCommand([]);
        self::assertFileExists($this->fileadminPath . 'tiny.png.webp');
        $firstSize = (int) \filesize($this->fileadminPath . 'tiny.png.webp');

        // Re-enqueue same tuple. Worker should see needsReprocessing()=false and skip the converter.
        $this->get(ConversionQueueRepository::class)->enqueue(
            (int) $file->getUid(),
            0,
            'Image.CropScaleMask',
            ['webp' => true]
        );
        $this->runCommand([]);

        self::assertSame(0, $this->countQueueRows(), 'Row removed even when conversion is skipped');
        self::assertSame($firstSize, (int) \filesize($this->fileadminPath . 'tiny.png.webp'), 'Webp must not be re-written when current');
    }

    #[Test]
    public function queueModeSkipsMissingFiles(): void
    {
        $this->get(ConversionQueueRepository::class)->enqueue(99999, 0, 'Image.CropScaleMask', []);

        $exitCode = $this->runCommand([]);

        self::assertSame(0, $exitCode);
        self::assertSame(0, $this->countQueueRows(), 'Row should be removed even for missing files');
    }

    #[Test]
    public function folderModeConvertsImagesWithoutSibling(): void
    {
        \copy(__DIR__ . '/../Fixtures/Images/tiny.png', $this->fileadminPath . 'other.png');

        $exitCode = $this->runCommand(['--folder' => 'fileadmin']);

        self::assertSame(0, $exitCode);
        self::assertFileExists($this->fileadminPath . 'other.png.webp');
        self::assertFileExists($this->fileadminPath . 'tiny.png.webp');
    }

    #[Test]
    public function folderModeSkipsImagesWithExistingSibling(): void
    {
        \file_put_contents($this->fileadminPath . 'tiny.png.webp', 'stale-content');
        $originalContents = \file_get_contents($this->fileadminPath . 'tiny.png.webp');

        $this->runCommand(['--folder' => 'fileadmin']);

        self::assertSame($originalContents, \file_get_contents($this->fileadminPath . 'tiny.png.webp'), 'Existing sibling must not be overwritten');
    }

    #[Test]
    public function commandSurvivesEmptyQueue(): void
    {
        // Smoke test: with no rows and no folder argument, the command exits
        // cleanly. Exercises the lock acquisition + release path. True
        // cross-process lock contention is not testable in a single-PID
        // PHPUnit run because flock() is process-reentrant; production
        // correctness rests on the LockingStrategy contract.
        self::assertSame(0, $this->runCommand([]));
    }

    #[Test]
    public function folderModeRejectsPathOutsideWebRoot(): void
    {
        $exitCode = $this->runCommand(['--folder' => '../../../etc']);

        self::assertSame(1, $exitCode);
    }

    #[Test]
    public function folderModeRejectsSiblingPrefixPath(): void
    {
        // /public_evil must not match /public via plain str_starts_with.
        $webRoot = \realpath(\TYPO3\CMS\Core\Core\Environment::getPublicPath());
        $sibling = \dirname($webRoot) . '/' . \basename($webRoot) . '_evil';
        @\mkdir($sibling, 0o777, true);
        try {
            $exitCode = $this->runCommand(['--folder' => '../' . \basename($sibling)]);
            self::assertSame(1, $exitCode);
        } finally {
            @\rmdir($sibling);
        }
    }

    #[Test]
    public function folderModeRemovesConvertedFileWhenLargerThanOriginal(): void
    {
        // Use a fixture isolated from other tests' tiny.png handling.
        \copy(__DIR__ . '/../Fixtures/Images/tiny.png', $this->fileadminPath . 'oversize.png');
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['webp']['converter']
            = \Plan2net\Webp\Tests\Functional\Fixtures\Doubles\RecordingConverter::class;

        $this->runCommand(['--folder' => 'fileadmin']);

        self::assertFileDoesNotExist(
            $this->fileadminPath . 'oversize.png.webp',
            'Oversize converter output must be unlinked, matching the FAL-mode guard'
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->get(StorageRepository::class)->findAll();

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/Database/sys_file.csv');

        $this->fileadminPath = $this->instancePath . '/fileadmin/';
        if (!\is_dir($this->fileadminPath)) {
            \mkdir($this->fileadminPath, 0o777, true);
        }
        \copy(__DIR__ . '/../Fixtures/Images/tiny.png', $this->fileadminPath . 'tiny.png');

        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['webp'] = [
            'converter' => DeterministicWebpConverter::class,
            'parameters' => 'image/jpeg::-quality 85|image/png::-quality 75|image/gif::-quality 85',
            'mime_types' => 'image/jpeg,image/png,image/gif',
            'convert_all' => '1',
            'exclude_directories' => '',
            'silent' => '0',
            'use_system_settings' => '0',
            'hide_webp' => '1',
            'filter_pattern' => '/\\.(jpe?g|png|gif)\\.webp$/i',
            'async' => '1',
            'async_throttle_ms' => '0',
        ];
    }

    private function runCommand(array $arguments): int
    {
        $tester = new CommandTester($this->get(ProcessConversionQueueCommand::class));

        return $tester->execute($arguments);
    }

    private function getFile(int $uid): File
    {
        return $this->get(ResourceFactory::class)->getFileObject($uid);
    }

    private function countQueueRows(): int
    {
        return (int) $this->getConnectionPool()
            ->getConnectionForTable('tx_webp_queue')
            ->count('uid', 'tx_webp_queue', []);
    }
}
