<?php

declare(strict_types=1);

namespace Plan2net\Webp\Tests\Functional\Converter;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Plan2net\Webp\Converter\Exception\UnsupportedFormatException;
use Plan2net\Webp\Converter\PhpGdConverter;
use Plan2net\Webp\Format\OutputFormat;
use Plan2net\Webp\Service\Configuration;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class PhpGdConverterTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['plan2net/webp'];

    private ?string $workingDirectory = null;

    #[Test]
    public function pngConvertsToValidWebpFile(): void
    {
        $sourceFile = $this->workingDirectory . '/tiny.png';
        \copy(__DIR__ . '/../Fixtures/Images/tiny.png', $sourceFile);
        $targetFile = $sourceFile . '.webp';

        $converter = new PhpGdConverter('-quality 80', $this->createConfiguration());
        $converter->convertTo($sourceFile, $targetFile, OutputFormat::Webp);

        self::assertFileExists($targetFile, 'WebP sibling must be created');
        $header = (string) \file_get_contents($targetFile, false, null, 0, 12);
        self::assertSame('RIFF', \substr($header, 0, 4), 'WebP file must start with RIFF magic');
        self::assertSame('WEBP', \substr($header, 8, 4), 'WebP file must declare WEBP form');
    }

    #[Test]
    #[DataProvider('unsupportedFormatProvider')]
    public function throwsForNonWebpFormats(OutputFormat $format): void
    {
        $sourceFile = $this->workingDirectory . '/tiny.png';
        \copy(__DIR__ . '/../Fixtures/Images/tiny.png', $sourceFile);
        $targetFile = $sourceFile . $format->suffix();

        $converter = new PhpGdConverter('-quality 80', $this->createConfiguration());

        $this->expectException(UnsupportedFormatException::class);
        $this->expectExceptionMessage($format->value);

        $converter->convertTo($sourceFile, $targetFile, $format);
    }

    public static function unsupportedFormatProvider(): array
    {
        return [
            'avif' => [OutputFormat::Avif],
            'jxl' => [OutputFormat::Jxl],
        ];
    }

    #[Test]
    public function transparencyIsPreservedInTheWebpSibling(): void
    {
        // Fixture is 16×16: left half (x<8) opaque blue, right half fully transparent.
        $sourceFile = $this->workingDirectory . '/transparent.png';
        \copy(__DIR__ . '/../Fixtures/Images/transparent.png', $sourceFile);
        $targetFile = $sourceFile . '.webp';

        $converter = new PhpGdConverter('-quality 80', $this->createConfiguration());
        $converter->convertTo($sourceFile, $targetFile, OutputFormat::Webp);

        $webp = \imagecreatefromwebp($targetFile);
        self::assertNotFalse($webp, 'Generated WebP must be readable');
        $opaqueAlpha = (\imagecolorat($webp, 4, 8) >> 24) & 0x7F;
        $transparentAlpha = (\imagecolorat($webp, 12, 8) >> 24) & 0x7F;
        \imagedestroy($webp);

        self::assertSame(0, $opaqueAlpha, 'Opaque pixel must stay opaque');
        self::assertSame(127, $transparentAlpha, 'Transparent pixel must stay fully transparent');
    }

    #[Test]
    #[DataProvider('qualityClampProvider')]
    public function parsedQualityIsClampedToValidRange(string $parameters, int $expected): void
    {
        $converter = new PhpGdConverter($parameters, $this->createConfiguration());
        $method = new \ReflectionMethod($converter, 'getQuality');

        self::assertSame($expected, $method->invoke($converter));
    }

    public static function qualityClampProvider(): array
    {
        return [
            'in-range stays as-is' => ['-quality 85', 85],
            'three-digit above 100 clamps to 100' => ['-quality 200', 100],
            'edge value 100 stays' => ['-quality 100', 100],
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (!\function_exists('imagewebp')) {
            self::markTestSkipped('PHP GD lacks imagewebp() — rebuild PHP-GD with libwebp');
        }

        $this->workingDirectory = \sys_get_temp_dir() . '/webp-gd-' . \bin2hex(\random_bytes(4));
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
        parent::tearDown();
    }

    private function createConfiguration(): Configuration
    {
        $extensionConfiguration = $this->createMock(ExtensionConfiguration::class);
        $extensionConfiguration->method('get')->with('webp')->willReturn([]);

        return new Configuration($extensionConfiguration);
    }
}
