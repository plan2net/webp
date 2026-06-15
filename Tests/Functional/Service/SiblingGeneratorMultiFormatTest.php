<?php

declare(strict_types=1);

namespace Plan2net\Webp\Tests\Functional\Service;

use PHPUnit\Framework\Attributes\Test;
use Plan2net\Webp\Converter\PhpGdConverter;
use Plan2net\Webp\Tests\Functional\Fixtures\Doubles\CapturingConverter;
use Plan2net\Webp\Tests\Functional\Fixtures\Doubles\DeterministicWebpConverter;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class SiblingGeneratorMultiFormatTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = [
        'install',
        'scheduler',
    ];

    protected array $testExtensionsToLoad = [
        'plan2net/webp',
    ];

    #[Test]
    public function twoEnabledFormatsProduceTwoProcessedFileRows(): void
    {
        $this->applyConfig([
            'converter' => DeterministicWebpConverter::class,
            'parameters' => 'image/png::Q=80',
            'converter_avif' => DeterministicWebpConverter::class,
            'parameters_avif' => 'image/png::Q=60',
            'mime_types' => 'image/png',
            'mime_types_avif' => 'image/png',
            'formats_enabled' => 'webp,avif',
        ]);

        $file = $this->get(ResourceFactory::class)->getFileObject(1);
        $file->process(ProcessedFile::CONTEXT_IMAGECROPSCALEMASK, ['width' => 16, 'height' => 16]);

        $formats = $this->fetchSiblingFormats((int) $file->getUid());

        self::assertContains('webp', $formats, 'WebP row must exist');
        self::assertContains('avif', $formats, 'AVIF row must exist');
    }

    #[Test]
    public function siblingRowsCarryVariantDimensionsNotOriginalDimensions(): void
    {
        $this->applyConfig([
            'converter' => DeterministicWebpConverter::class,
            'parameters' => 'image/png::Q=80',
            'converter_avif' => DeterministicWebpConverter::class,
            'parameters_avif' => 'image/png::Q=60',
            'mime_types' => 'image/png',
            'mime_types_avif' => 'image/png',
            'formats_enabled' => 'webp,avif',
        ]);

        $file = $this->get(ResourceFactory::class)->getFileObject(1);
        $file->process(ProcessedFile::CONTEXT_IMAGECROPSCALEMASK, ['width' => 16, 'height' => 16]);

        $rows = $this->getConnectionPool()
            ->getConnectionForTable('sys_file_processedfile')
            ->select(['configuration', 'width', 'height'], 'sys_file_processedfile', ['original' => (int) $file->getUid()])
            ->fetchAllAssociative();

        foreach ($rows as $row) {
            $cfg = null === $row['configuration'] ? [] : \unserialize($row['configuration']);
            if (!\is_array($cfg) || empty($cfg['format'])) {
                continue;
            }
            self::assertSame(16, (int) $row['width'], \sprintf('%s row must carry the variant width (16), not the original (64)', $cfg['format']));
            self::assertSame(16, (int) $row['height'], \sprintf('%s row must carry the variant height (16), not the original (64)', $cfg['format']));
        }
    }

    #[Test]
    public function avifFailureDoesNotBlockWebp(): void
    {
        $this->applyConfig([
            'converter' => DeterministicWebpConverter::class,
            'parameters' => 'image/png::Q=80',
            'converter_avif' => PhpGdConverter::class,
            'parameters_avif' => 'image/png::Q=70',
            'mime_types' => 'image/png',
            'mime_types_avif' => 'image/png',
            'formats_enabled' => 'webp,avif',
        ]);

        $file = $this->get(ResourceFactory::class)->getFileObject(1);
        $file->process(ProcessedFile::CONTEXT_IMAGECROPSCALEMASK, ['width' => 16, 'height' => 16]);

        $formats = $this->fetchSiblingFormats((int) $file->getUid());

        self::assertContains('webp', $formats, 'WebP must still be produced when AVIF refuses');
        self::assertNotContains('avif', $formats, 'PhpGd refuses AVIF — no row');
    }

    #[Test]
    public function perFileQualityOverrideReachesTheConverter(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/Database/sys_file_metadata.csv');
        $this->applyConfig([
            'converter' => CapturingConverter::class,
            'parameters' => 'image/png::Q=80',
            'mime_types' => 'image/png',
            'formats_enabled' => 'webp',
        ]);

        $file = $this->get(ResourceFactory::class)->getFileObject(1);
        $file->process(ProcessedFile::CONTEXT_IMAGECROPSCALEMASK, ['width' => 16, 'height' => 16]);

        self::assertContains('Q=50', CapturingConverter::$receivedParameters);
        self::assertNotContains('Q=80', CapturingConverter::$receivedParameters);
    }

    #[Test]
    public function withoutOverrideTheGlobalQualityIsUsed(): void
    {
        $this->applyConfig([
            'converter' => CapturingConverter::class,
            'parameters' => 'image/png::Q=80',
            'mime_types' => 'image/png',
            'formats_enabled' => 'webp',
        ]);

        $file = $this->get(ResourceFactory::class)->getFileObject(1);
        $file->process(ProcessedFile::CONTEXT_IMAGECROPSCALEMASK, ['width' => 16, 'height' => 16]);

        self::assertContains('Q=80', CapturingConverter::$receivedParameters);
    }

    #[Test]
    public function overrideIsRecordedInTheProcessedFileConfiguration(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/Database/sys_file_metadata.csv');
        $this->applyConfig([
            'converter' => CapturingConverter::class,
            'parameters' => 'image/png::Q=80',
            'mime_types' => 'image/png',
            'formats_enabled' => 'webp',
        ]);

        $file = $this->get(ResourceFactory::class)->getFileObject(1);
        $file->process(ProcessedFile::CONTEXT_IMAGECROPSCALEMASK, ['width' => 16, 'height' => 16]);

        $configurations = $this->fetchSiblingConfigurations((int) $file->getUid());
        self::assertNotEmpty($configurations);
        foreach ($configurations as $configuration) {
            self::assertSame(50, $configuration['tx_webp_quality'] ?? null);
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        CapturingConverter::$receivedParameters = [];

        $this->get(StorageRepository::class)->findAll();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/Database/sys_file.csv');

        $fileadminPath = $this->instancePath . '/fileadmin/';
        if (!\is_dir($fileadminPath)) {
            \mkdir($fileadminPath, 0o777, true);
        }
        \copy(__DIR__ . '/../Fixtures/Images/tiny.png', $fileadminPath . 'tiny.png');
        \copy(__DIR__ . '/../Fixtures/Images/tiny.webp', $fileadminPath . 'tiny.webp');
    }

    /**
     * @return list<string>
     */
    private function fetchSiblingFormats(int $originalUid): array
    {
        $rows = $this->getConnectionPool()
            ->getConnectionForTable('sys_file_processedfile')
            ->select(['configuration'], 'sys_file_processedfile', ['original' => $originalUid])
            ->fetchAllAssociative();

        $formats = [];
        foreach ($rows as $row) {
            $cfg = null === $row['configuration'] ? [] : \unserialize($row['configuration']);
            if (\is_array($cfg) && !empty($cfg['format'])) {
                $formats[] = $cfg['format'];
            }
        }

        return $formats;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchSiblingConfigurations(int $originalUid): array
    {
        $rows = $this->getConnectionPool()
            ->getConnectionForTable('sys_file_processedfile')
            ->select(['configuration'], 'sys_file_processedfile', ['original' => $originalUid])
            ->fetchAllAssociative();

        $configurations = [];
        foreach ($rows as $row) {
            $cfg = null === $row['configuration'] ? [] : \unserialize($row['configuration']);
            if (\is_array($cfg) && !empty($cfg['format'])) {
                $configurations[] = $cfg;
            }
        }

        return $configurations;
    }

    private function applyConfig(array $overrides): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['webp'] = $overrides + [
            'convert_all' => '1',
            'use_system_settings' => '0',
            'silent' => '0',
            'hide_webp' => '1',
            'async' => '0',
            'filter_pattern' => '/\\.(jpe?g|png|gif)\\.(webp|avif|jxl)$/i',
        ];
    }
}
