<?php

declare(strict_types=1);

namespace Plan2net\Webp\Tests\Functional\Converter;

use PHPUnit\Framework\Attributes\Test;
use Plan2net\Webp\Converter\MagickConverter;
use Plan2net\Webp\Format\OutputFormat;
use Plan2net\Webp\Service\Configuration;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class MagickConverterTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['plan2net/webp'];

    private ?string $workingDirectory = null;
    private ?string $originalProcessor = null;

    #[Test]
    public function pngConvertsToValidWebpFile(): void
    {
        $sourceFile = $this->workingDirectory . '/tiny.png';
        \copy(__DIR__ . '/../Fixtures/Images/tiny.png', $sourceFile);
        $targetFile = $sourceFile . '.webp';

        $converter = new MagickConverter('-quality 80', $this->createConfiguration());
        $converter->convertTo($sourceFile, $targetFile, OutputFormat::Webp);

        self::assertFileExists($targetFile, 'WebP sibling must be created');
        $header = (string) \file_get_contents($targetFile, false, null, 0, 12);
        self::assertSame('RIFF', \substr($header, 0, 4), 'WebP file must start with RIFF magic');
        self::assertSame('WEBP', \substr($header, 8, 4), 'WebP file must declare WEBP form');
    }

    #[Test]
    public function pngConvertsToValidAvifFile(): void
    {
        // FunctionalTestCase pins GFX.processor=GraphicsMagick. GM cannot WRITE
        // AVIF (read-only on Debian); ImageMagick with libheif's AV1 encoder can.
        $this->originalProcessor = $GLOBALS['TYPO3_CONF_VARS']['GFX']['processor'] ?? null;
        $GLOBALS['TYPO3_CONF_VARS']['GFX']['processor'] = 'ImageMagick';

        if (!self::imageMagickCanWriteAvif()) {
            self::markTestSkipped('ImageMagick on this host cannot write AVIF (no libheif AV1 encoder delegate)');
        }

        $sourceFile = $this->workingDirectory . '/tiny.png';
        \copy(__DIR__ . '/../Fixtures/Images/tiny.png', $sourceFile);
        $targetFile = $sourceFile . '.avif';

        $converter = new MagickConverter('-quality 70', $this->createConfiguration());
        $converter->convertTo($sourceFile, $targetFile, OutputFormat::Avif);

        self::assertFileExists($targetFile);
        $header = (string) \file_get_contents($targetFile, false, null, 0, 12);
        self::assertSame('ftypavif', \substr($header, 4, 8));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->workingDirectory = \sys_get_temp_dir() . '/webp-magick-' . \bin2hex(\random_bytes(4));
        \mkdir($this->workingDirectory, 0o755, true);
    }

    protected function tearDown(): void
    {
        if (null !== $this->workingDirectory && \is_dir($this->workingDirectory)) {
            foreach (\scandir($this->workingDirectory) ?: [] as $entry) {
                if ('.' === $entry || '..' === $entry) {
                    continue;
                }
                @\unlink($this->workingDirectory . '/' . $entry);
            }
            @\rmdir($this->workingDirectory);
            $this->workingDirectory = null;
        }
        if (null !== $this->originalProcessor) {
            $GLOBALS['TYPO3_CONF_VARS']['GFX']['processor'] = $this->originalProcessor;
            $this->originalProcessor = null;
        }
        parent::tearDown();
    }

    private function createConfiguration(): Configuration
    {
        $extensionConfiguration = $this->createMock(ExtensionConfiguration::class);
        $extensionConfiguration->method('get')->with('webp')->willReturn([]);

        return new Configuration($extensionConfiguration);
    }

    private static function imageMagickCanWriteAvif(): bool
    {
        $probe = \sys_get_temp_dir() . '/webp-magick-probe-' . \bin2hex(\random_bytes(4)) . '.avif';
        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = @\proc_open(['convert', 'rose:', $probe], $descriptors, $pipes);
        if (!\is_resource($process)) {
            return false;
        }
        \fclose($pipes[0]);
        \fclose($pipes[1]);
        \fclose($pipes[2]);
        \proc_close($process);

        $exists = \is_file($probe) && \filesize($probe) > 0;
        @\unlink($probe);

        return $exists;
    }
}
