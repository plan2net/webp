<?php

declare(strict_types=1);

namespace Plan2net\Webp\Tests\Functional\Command;

use PHPUnit\Framework\Attributes\Test;
use Plan2net\Webp\Command\DiagnoseCommand;
use Plan2net\Webp\Tests\Functional\Fixtures\Doubles\DeterministicWebpConverter;
use Plan2net\Webp\Tests\Functional\Fixtures\Doubles\JpegOutputConverter;
use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class DiagnoseCommandTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = [
        'install',
        'scheduler',
    ];

    protected array $testExtensionsToLoad = [
        'plan2net/webp',
    ];

    #[Test]
    public function listsSiblingsWhoseContentDoesNotMatchTheirSuffix(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['webp'] = [
            'converter' => DeterministicWebpConverter::class,
            'parameters' => 'image/png::-quality 80',
            'mime_types' => 'image/png',
            'converter_avif' => DeterministicWebpConverter::class,
            'parameters_avif' => 'image/png::-quality 60',
            'mime_types_avif' => 'image/png',
            'formats_enabled' => 'webp,avif',
            'convert_all' => '1',
            'async' => '0',
        ];
        $variant = $this->get(ResourceFactory::class)->getFileObject(1)
            ->process(ProcessedFile::CONTEXT_IMAGECROPSCALEMASK, ['width' => 16, 'height' => 16]);
        $variantPath = $variant->getForLocalProcessing(false);
        // As written by an ImageMagick build without an AVIF encoder.
        \file_put_contents($variantPath . '.avif', JpegOutputConverter::BYTES);

        $tester = new CommandTester($this->get(DiagnoseCommand::class));
        $tester->execute([]);
        $display = $tester->getDisplay();

        self::assertStringContainsString($variant->getIdentifier() . '.avif (JPEG)', $display);
        self::assertStringNotContainsString($variant->getIdentifier() . '.webp (', $display);
        self::assertStringContainsString('[avif] siblings with content that is not AVIF: 1 of 1 checked', $display);
        self::assertStringContainsString('clear the page cache', $display);
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
}
