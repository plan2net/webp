<?php

declare(strict_types=1);

namespace Plan2net\Webp\Tests\Functional\EventListener;

use PHPUnit\Framework\Attributes\Test;
use Plan2net\Webp\Converter\PhpGdConverter;
use Plan2net\Webp\EventListener\AfterFileProcessing;
use Plan2net\Webp\Tests\Functional\Fixtures\Doubles\RecordingConverter;
use TYPO3\CMS\Core\Resource\Driver\DriverInterface;
use TYPO3\CMS\Core\Resource\Event\AfterFileProcessingEvent;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileReference;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class AfterFileProcessingFunctionalTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'plan2net/webp',
    ];

    #[Test]
    public function happyPathCreatesSiblingWebpForPng(): void
    {
        $file = $this->getFile(1);

        $processed = $file->process(
            ProcessedFile::CONTEXT_IMAGECROPSCALEMASK,
            ['width' => 16, 'height' => 16],
        );

        self::assertFileExists($processed->getForLocalProcessing(false) . '.webp');
        self::assertSame(1, $this->countWebpRowsForOriginal((int) $file->getUid()));
    }

    #[Test]
    public function skipsUnsupportedMimeType(): void
    {
        $this->applyConfigOverride('mime_types', 'image/jpeg,image/gif');

        $file = $this->getFile(1);
        $file->process(ProcessedFile::CONTEXT_IMAGECROPSCALEMASK, ['width' => 16, 'height' => 16]);

        self::assertSame(0, $this->countWebpRowsForOriginal((int) $file->getUid()));
    }

    #[Test]
    public function skipsWhenTaskTypeIsNotCropScaleMask(): void
    {
        $file = $this->getFile(1);
        $file->process(ProcessedFile::CONTEXT_IMAGEPREVIEW, ['width' => 16, 'height' => 16]);

        self::assertSame(0, $this->countWebpRowsForOriginal((int) $file->getUid()));
    }

    #[Test]
    public function skipsWhenConvertAllIsFalseAndFileOutsideProcessingFolder(): void
    {
        $this->applyConfigOverride('convert_all', '0');

        $file = $this->getFile(1);
        $file->process(
            ProcessedFile::CONTEXT_IMAGECROPSCALEMASK,
            ['width' => 32, 'height' => 32, 'noScale' => true],
        );

        self::assertSame(0, $this->countWebpRowsForOriginal((int) $file->getUid()));
    }

    #[Test]
    public function skipsExcludedDirectory(): void
    {
        $this->applyConfigOverride('exclude_directories', '/fileadmin');

        $file = $this->getFile(1);
        $file->process(ProcessedFile::CONTEXT_IMAGECROPSCALEMASK, ['width' => 16, 'height' => 16]);

        self::assertSame(0, $this->countWebpRowsForOriginal((int) $file->getUid()));
    }

    #[Test]
    public function normalizesFileReferenceToFile(): void
    {
        $file = $this->getFile(1);
        $reference = $this->getFileReference(101);

        $processed = $file->process(
            ProcessedFile::CONTEXT_IMAGECROPSCALEMASK,
            ['width' => 16, 'height' => 16],
        );
        $existingWebp = $this->fetchWebpRow((int) $file->getUid());
        if (null !== $existingWebp) {
            $this->getConnectionPool()
                ->getConnectionForTable('sys_file_processedfile')
                ->delete('sys_file_processedfile', ['uid' => $existingWebp['uid']]);
        }
        @unlink($processed->getForLocalProcessing(false) . '.webp');

        $event = new AfterFileProcessingEvent(
            $this->createMock(DriverInterface::class),
            $processed,
            $reference,
            'Image.CropScaleMask',
            ['width' => 16, 'height' => 16],
        );

        $this->get(AfterFileProcessing::class)($event);

        $webpRow = $this->fetchWebpRow((int) $file->getUid());
        self::assertNotNull($webpRow, 'Listener should produce a webp row keyed by the underlying sys_file UID');
        self::assertSame((int) $file->getUid(), (int) $webpRow['original'], 'original column must be the sys_file UID');
        self::assertNotSame((int) $reference->getUid(), (int) $webpRow['original'], 'original column must NOT be the FileReference UID');
    }

    #[Test]
    public function skipsFileAlreadyEndingInWebp(): void
    {
        $this->applyConfigOverride('mime_types', 'image/jpeg,image/png,image/gif,image/webp');

        $file = $this->getFile(2);

        $processed = $file->process(
            ProcessedFile::CONTEXT_IMAGECROPSCALEMASK,
            ['width' => 16, 'height' => 16],
        );

        self::assertFileDoesNotExist($processed->getForLocalProcessing(false) . '.webp');
        self::assertSame(0, $this->countWebpRowsForOriginal((int) $file->getUid()));
    }

    #[Test]
    public function recordsFailedAttemptWhenConvertedFileLargerThanOriginal(): void
    {
        $this->applyConfigOverride('converter', RecordingConverter::class);

        $file = $this->getFile(1);

        $processed = $file->process(
            ProcessedFile::CONTEXT_IMAGECROPSCALEMASK,
            ['width' => 16, 'height' => 16],
        );

        self::assertSame(1, $this->countFailedRows((int) $file->getUid()));
        self::assertFileDoesNotExist($processed->getForLocalProcessing(false) . '.webp');
        self::assertSame(0, $this->countWebpRowsForOriginal((int) $file->getUid()));

        // Second invocation: wasAttempted() must return true and short-circuit
        // before record() is called again, otherwise the failed-row count would grow.
        $file->process(ProcessedFile::CONTEXT_IMAGECROPSCALEMASK, ['width' => 16, 'height' => 16]);

        self::assertSame(1, $this->countFailedRows((int) $file->getUid()), 'wasAttempted() must short-circuit; no duplicate insert');
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Trigger auto-creation of the default fileadmin storage (UID 1).
        $this->get(StorageRepository::class)->findAll();

        $storageRow = $this->getConnectionPool()
            ->getConnectionForTable('sys_file_storage')
            ->select(['uid', 'driver'], 'sys_file_storage', ['uid' => 1])
            ->fetchAssociative();
        self::assertNotFalse($storageRow, 'Expected default fileadmin storage (UID 1) to exist');
        self::assertSame('Local', $storageRow['driver']);

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/Database/sys_file.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/Database/sys_file_reference.csv');

        $fileadminPath = $this->instancePath . '/fileadmin/';
        if (!is_dir($fileadminPath)) {
            mkdir($fileadminPath, 0o777, true);
        }
        copy(__DIR__ . '/../Fixtures/Images/tiny.png', $fileadminPath . 'tiny.png');
        copy(__DIR__ . '/../Fixtures/Images/tiny.webp', $fileadminPath . 'tiny.webp');

        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['webp'] = [
            'converter' => PhpGdConverter::class,
            'parameters' => 'image/jpeg::-quality 85|image/png::-quality 75|image/gif::-quality 85',
            'mime_types' => 'image/jpeg,image/png,image/gif',
            'convert_all' => '1',
            'exclude_directories' => '',
            'silent' => '0',
            'use_system_settings' => '0',
            'hide_webp' => '1',
            'filter_pattern' => '/\\.(jpe?g|png|gif)\\.webp$/i',
        ];
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    private function getFile(int $uid): File
    {
        return $this->get(ResourceFactory::class)->getFileObject($uid);
    }

    private function getFileReference(int $referenceUid): FileReference
    {
        return $this->get(ResourceFactory::class)->getFileReferenceObject($referenceUid);
    }

    private function countWebpRowsForOriginal(int $originalUid): int
    {
        $rows = $this->getConnectionPool()
            ->getConnectionForTable('sys_file_processedfile')
            ->select(['configuration'], 'sys_file_processedfile', ['original' => $originalUid])
            ->fetchAllAssociative();

        $count = 0;
        foreach ($rows as $row) {
            $cfg = null === $row['configuration'] ? [] : unserialize($row['configuration']);
            if (is_array($cfg) && !empty($cfg['webp'])) {
                ++$count;
            }
        }

        return $count;
    }

    private function fetchWebpRow(int $originalUid): ?array
    {
        $rows = $this->getConnectionPool()
            ->getConnectionForTable('sys_file_processedfile')
            ->select(['*'], 'sys_file_processedfile', ['original' => $originalUid])
            ->fetchAllAssociative();

        foreach ($rows as $row) {
            $cfg = null === $row['configuration'] ? [] : unserialize($row['configuration']);
            if (is_array($cfg) && !empty($cfg['webp'])) {
                return $row;
            }
        }

        return null;
    }

    private function countFailedRows(int $fileId): int
    {
        return (int) $this->getConnectionPool()
            ->getConnectionForTable('tx_webp_failed')
            ->count('uid', 'tx_webp_failed', ['file_id' => $fileId]);
    }

    private function applyConfigOverride(string $key, string $value): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['webp'][$key] = $value;
    }
}
