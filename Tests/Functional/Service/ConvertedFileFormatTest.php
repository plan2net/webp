<?php

declare(strict_types=1);

namespace Plan2net\Webp\Tests\Functional\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Plan2net\Webp\Converter\Exception\ConvertedFileNotInRequestedFormatException;
use Plan2net\Webp\Format\OutputFormat;
use Plan2net\Webp\Service\SiblingGenerator;
use Plan2net\Webp\Tests\Functional\Fixtures\Doubles\FixtureCopyingConverter;
use Plan2net\Webp\Tests\Functional\Fixtures\Doubles\JpegOutputConverter;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class ConvertedFileFormatTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = [
        'install',
        'scheduler',
    ];

    protected array $testExtensionsToLoad = [
        'plan2net/webp',
    ];

    /**
     * @return array<string, array{OutputFormat}>
     */
    public static function formats(): array
    {
        return [
            'avif' => [OutputFormat::Avif],
            'webp' => [OutputFormat::Webp],
        ];
    }

    #[Test]
    #[DataProvider('formats')]
    public function aConvertedFileInAnotherFormatIsDeletedAndRejected(OutputFormat $format): void
    {
        $this->applyConfig(JpegOutputConverter::class);
        $targetPath = $this->instancePath . '/typo3temp/rejected' . $format->suffix();

        try {
            $this->get(SiblingGenerator::class)->convertFilePath($this->instancePath . '/fileadmin/tiny.png', $targetPath, 'image/png::-quality 60', $format);
            self::fail('A JPEG written under a ' . $format->suffix() . ' name must be rejected');
        } catch (ConvertedFileNotInRequestedFormatException $exception) {
            self::assertStringContainsString($format->label(), $exception->getMessage());
            self::assertStringContainsString('JPEG', $exception->getMessage());
        }

        self::assertFileDoesNotExist($targetPath);
    }

    /**
     * @return array<string, array{OutputFormat, string}>
     */
    public static function realFiles(): array
    {
        return [
            'webp' => [OutputFormat::Webp, 'tiny.webp'],
            'avif' => [OutputFormat::Avif, 'tiny.avif'],
            'avif with mif1 as major brand' => [OutputFormat::Avif, 'tiny-mif1.avif'],
        ];
    }

    #[Test]
    #[DataProvider('realFiles')]
    public function aConvertedFileInTheRequestedFormatIsKept(OutputFormat $format, string $fixture): void
    {
        $this->applyConfig(FixtureCopyingConverter::class);
        FixtureCopyingConverter::$fixture = __DIR__ . '/../Fixtures/Images/' . $fixture;
        $targetPath = $this->instancePath . '/typo3temp/kept' . $format->suffix();

        $size = $this->get(SiblingGenerator::class)->convertFilePath($this->instancePath . '/fileadmin/tiny.png', $targetPath, 'image/png::-quality 60', $format);

        self::assertFileExists($targetPath);
        self::assertSame(\filesize(FixtureCopyingConverter::$fixture), $size);
    }

    #[Test]
    #[DataProvider('formats')]
    public function aConvertedFileInAnotherFormatIsRecordedAsFailedAndNotPublished(OutputFormat $format): void
    {
        $this->applyConfig(JpegOutputConverter::class);

        $file = $this->get(ResourceFactory::class)->getFileObject(1);
        $processed = $file->process(ProcessedFile::CONTEXT_IMAGECROPSCALEMASK, ['width' => 16, 'height' => 16]);

        self::assertFileDoesNotExist($processed->getForLocalProcessing(false) . $format->suffix());
        self::assertNotContains($format->value, $this->siblingFormats());
        self::assertSame(1, $this->failedRows($format));
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->get(StorageRepository::class)->findAll();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/Database/sys_file.csv');

        $fileadminPath = $this->instancePath . '/fileadmin/';
        if (!\is_dir($fileadminPath)) {
            \mkdir($fileadminPath, 0o777, true);
        }
        \copy(__DIR__ . '/../Fixtures/Images/tiny.png', $fileadminPath . 'tiny.png');
    }

    /**
     * @return list<string>
     */
    private function siblingFormats(): array
    {
        $rows = $this->getConnectionPool()
            ->getConnectionForTable('sys_file_processedfile')
            ->select(['configuration'], 'sys_file_processedfile', ['original' => 1])
            ->fetchAllAssociative();

        $formats = [];
        foreach ($rows as $row) {
            $configuration = null === $row['configuration'] ? [] : \unserialize($row['configuration']);
            if (\is_array($configuration) && !empty($configuration['format'])) {
                $formats[] = $configuration['format'];
            }
        }

        return $formats;
    }

    private function failedRows(OutputFormat $format): int
    {
        return (int) $this->getConnectionPool()
            ->getConnectionForTable('tx_webp_failed')
            ->count('uid', 'tx_webp_failed', ['file_id' => 1, 'format' => $format->value]);
    }

    private function applyConfig(string $converterClass): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['webp'] = [
            'converter' => $converterClass,
            'parameters' => 'image/png::-quality 80',
            'mime_types' => 'image/png',
            'converter_avif' => $converterClass,
            'parameters_avif' => 'image/png::-quality 60',
            'mime_types_avif' => 'image/png',
            'formats_enabled' => 'webp,avif',
            'convert_all' => '1',
            'use_system_settings' => '0',
            'silent' => '0',
            'hide_webp' => '1',
            'async' => '0',
            'filter_pattern' => '/\\.(jpe?g|png|gif)\\.(webp|avif|jxl)$/i',
        ];
    }
}
