<?php

declare(strict_types=1);

namespace Plan2net\Webp\Tests\Functional\Converter;

use Jcupitt\Vips\Config as VipsConfig;
use Jcupitt\Vips\FFI as VipsFFI;
use Jcupitt\Vips\Image as VipsImage;
use PHPUnit\Framework\Attributes\Test;
use Plan2net\Webp\Converter\VipsConverter;
use Plan2net\Webp\Format\OutputFormat;
use Plan2net\Webp\Service\Configuration;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class VipsConverterTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = [
        'install',
        'scheduler',
    ];

    protected array $testExtensionsToLoad = [
        'plan2net/webp',
    ];

    private ?string $workingDirectory = null;

    #[Test]
    public function pngConvertsToValidWebpFile(): void
    {
        $sourceFile = $this->workingDirectory . '/tiny.png';
        \copy(__DIR__ . '/../Fixtures/Images/tiny.png', $sourceFile);
        $targetFile = $sourceFile . '.webp';

        $converter = new VipsConverter('Q=80 effort=4', $this->createConfiguration());
        $converter->convert($sourceFile, $targetFile);

        self::assertFileExists($targetFile, 'WebP sibling must be created');
        self::assertGreaterThan(0, \filesize($targetFile), 'WebP sibling must be non-empty');

        $header = (string) \file_get_contents($targetFile, false, null, 0, 12);
        self::assertSame('RIFF', \substr($header, 0, 4), 'WebP file must start with RIFF magic');
        self::assertSame('WEBP', \substr($header, 8, 4), 'WebP file must declare WEBP form');
    }

    #[Test]
    public function animatedGifConvertsToAnimatedWebp(): void
    {
        $sourceFile = $this->workingDirectory . '/tiny-animated.gif';
        \copy(__DIR__ . '/../Fixtures/Images/tiny-animated.gif', $sourceFile);
        $targetFile = $sourceFile . '.webp';

        $converter = new VipsConverter('Q=75 lossless=true mixed=true effort=4', $this->createConfiguration());
        $converter->convert($sourceFile, $targetFile);

        self::assertGreaterThan(1, $this->readPageCount($targetFile), 'animated GIF must produce a multi-frame WebP');
    }

    #[Test]
    public function stillGifConvertsToSingleFrameWebp(): void
    {
        $sourceFile = $this->workingDirectory . '/tiny-still.gif';
        \copy(__DIR__ . '/../Fixtures/Images/tiny-still.gif', $sourceFile);
        $targetFile = $sourceFile . '.webp';

        $converter = new VipsConverter('Q=75 lossless=true mixed=true effort=4', $this->createConfiguration());
        $converter->convert($sourceFile, $targetFile);

        self::assertSame(1, $this->readPageCount($targetFile), 'single-frame GIF must produce a single-frame WebP');
    }

    #[Test]
    public function pngConvertsToValidAvifFile(): void
    {
        if ('' === (string) VipsFFI::vips()->vips_foreign_find_save('probe.avif')) {
            self::markTestSkipped('libvips on this host lacks an .avif saver (libheif AV1 not built in)');
        }
        $sourceFile = $this->workingDirectory . '/tiny.png';
        \copy(__DIR__ . '/../Fixtures/Images/tiny.png', $sourceFile);
        $targetFile = $sourceFile . '.avif';

        $converter = new VipsConverter('Q=60 effort=4', $this->createConfiguration());
        try {
            $converter->convertTo($sourceFile, $targetFile, OutputFormat::Avif);
        } catch (\RuntimeException $exception) {
            if (\str_contains($exception->getMessage(), 'Unsupported compression')) {
                self::markTestSkipped('libheif on this host lacks an AV1 encoder plugin (heifsave reported Unsupported compression)');
            }
            throw $exception;
        }

        self::assertFileExists($targetFile, 'AVIF sibling must be created');
        $header = (string) \file_get_contents($targetFile, false, null, 0, 12);
        self::assertSame('ftypavif', \substr($header, 4, 8), 'AVIF must declare ftypavif box');
    }

    #[Test]
    public function pngConvertsToValidJxlFile(): void
    {
        if ('' === (string) VipsFFI::vips()->vips_foreign_find_save('probe.jxl')) {
            self::markTestSkipped('libvips on this host lacks a .jxl saver (libjxl not built in)');
        }
        $sourceFile = $this->workingDirectory . '/tiny.png';
        \copy(__DIR__ . '/../Fixtures/Images/tiny.png', $sourceFile);
        $targetFile = $sourceFile . '.jxl';

        $converter = new VipsConverter('Q=75 effort=7', $this->createConfiguration());
        $converter->convertTo($sourceFile, $targetFile, OutputFormat::Jxl);

        self::assertFileExists($targetFile, 'JXL sibling must be created');
        $magic = \bin2hex((string) \file_get_contents($targetFile, false, null, 0, 12));
        self::assertTrue(
            \str_starts_with($magic, 'ff0a') || \str_starts_with($magic, '0000000c4a584c20'),
            'JXL must start with raw 0xFF0A or ISOBMFF container 0x0000000C JXL ',
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (!\extension_loaded('ffi')) {
            self::markTestSkipped('ext-ffi not loaded');
        }
        $ffiEnable = \strtolower((string) \ini_get('ffi.enable'));
        if ('preload' === $ffiEnable
            || false === \filter_var($ffiEnable, \FILTER_VALIDATE_BOOLEAN, \FILTER_NULL_ON_FAILURE)
            || !\filter_var($ffiEnable, \FILTER_VALIDATE_BOOLEAN)
        ) {
            self::markTestSkipped('ffi.enable is not set to true (value: "' . $ffiEnable . '")');
        }
        if (!\class_exists(VipsImage::class)) {
            self::markTestSkipped('jcupitt/vips not installed');
        }
        try {
            VipsConfig::version();
        } catch (\Throwable $exception) {
            self::markTestSkipped('libvips shared library not reachable via FFI: ' . $exception->getMessage());
        }

        $this->workingDirectory = \sys_get_temp_dir() . '/webp-vips-' . \bin2hex(\random_bytes(4));
        \mkdir($this->workingDirectory, 0o755, true);
    }

    protected function tearDown(): void
    {
        if (null !== $this->workingDirectory) {
            $this->removeDirectory($this->workingDirectory);
            $this->workingDirectory = null;
        }
        parent::tearDown();
    }

    private function readPageCount(string $path): int
    {
        // libvips only writes the `n-pages` property when the image actually has more than one frame.
        // Single-frame WebPs omit it, so `get('n-pages')` would throw — fall back to 1 in that case.
        $image = VipsImage::newFromFile($path, ['n' => -1]);

        return 0 !== $image->getType('n-pages') ? (int) $image->get('n-pages') : 1;
    }

    private function createConfiguration(): Configuration
    {
        $extensionConfiguration = $this->createMock(ExtensionConfiguration::class);
        $extensionConfiguration->method('get')->with('webp')->willReturn([]);

        return new Configuration($extensionConfiguration);
    }

    private function removeDirectory(string $path): void
    {
        if (!\is_dir($path)) {
            return;
        }
        foreach (\scandir($path) as $entry) {
            if ('.' === $entry || '..' === $entry) {
                continue;
            }
            $full = $path . '/' . $entry;
            \is_dir($full) ? $this->removeDirectory($full) : @\unlink($full);
        }
        @\rmdir($path);
    }
}
