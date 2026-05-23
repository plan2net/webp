<?php

declare(strict_types=1);

namespace Plan2net\Webp\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Plan2net\Webp\Format\OutputFormat;
use Plan2net\Webp\Service\Configuration;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

final class ConfigurationTest extends TestCase
{
    #[Test]
    public function getConverterReturnsConfiguredClass(): void
    {
        $config = $this->configurationWith(['converter' => 'Plan2net\\Webp\\Converter\\PhpGdConverter']);

        self::assertSame('Plan2net\\Webp\\Converter\\PhpGdConverter', $config->getConverter());
    }

    #[Test]
    public function getConverterReturnsEmptyStringWhenKeyMissing(): void
    {
        $config = $this->configurationWith([]);

        self::assertSame('', $config->getConverter());
    }

    #[Test]
    public function getParametersReturnsRawString(): void
    {
        $config = $this->configurationWith(['parameters' => 'image/jpeg::-quality 85|image/png::-quality 75']);

        self::assertSame('image/jpeg::-quality 85|image/png::-quality 75', $config->getParameters());
    }

    #[Test]
    public function getParametersReturnsEmptyStringWhenKeyMissing(): void
    {
        $config = $this->configurationWith([]);

        self::assertSame('', $config->getParameters());
    }

    #[Test]
    public function isSupportedMimeTypeMatchesCaseInsensitively(): void
    {
        $config = $this->configurationWith(['mime_types' => 'image/jpeg,image/png']);

        self::assertTrue($config->isSupportedMimeType('IMAGE/JPEG'));
        self::assertTrue($config->isSupportedMimeType('image/png'));
        self::assertFalse($config->isSupportedMimeType('image/gif'));
    }

    #[Test]
    public function isSupportedMimeTypeReturnsFalseWhenListEmpty(): void
    {
        $config = $this->configurationWith([]);

        self::assertFalse($config->isSupportedMimeType('image/jpeg'));
    }

    #[Test]
    #[DataProvider('booleanAccessorProvider')]
    public function booleanAccessorsCoerceStringFlag(string $key, string $accessor): void
    {
        self::assertTrue($this->configurationWith([$key => '1'])->{$accessor}());
        self::assertFalse($this->configurationWith([$key => '0'])->{$accessor}());
        self::assertFalse($this->configurationWith([])->{$accessor}());
    }

    public static function booleanAccessorProvider(): array
    {
        return [
            'convert_all' => ['convert_all', 'isConvertAll'],
            'silent' => ['silent', 'isSilent'],
            'use_system_settings' => ['use_system_settings', 'isUseSystemSettings'],
            'hide_webp' => ['hide_webp', 'isHideSiblings'],
            'async' => ['async', 'isAsync'],
        ];
    }

    #[Test]
    public function getAsyncThrottleMsReturnsZeroByDefault(): void
    {
        self::assertSame(0, $this->configurationWith([])->getAsyncThrottleMs());
    }

    #[Test]
    public function getAsyncThrottleMsReturnsConfiguredInteger(): void
    {
        self::assertSame(500, $this->configurationWith(['async_throttle_ms' => '500'])->getAsyncThrottleMs());
    }

    #[Test]
    public function getMimeTypesReturnsLowercasedList(): void
    {
        self::assertSame(
            ['image/jpeg', 'image/png'],
            $this->configurationWith(['mime_types' => ' Image/JPEG , image/png '])->getMimeTypes()
        );
    }

    #[Test]
    public function getMimeTypesReturnsEmptyListWhenMissing(): void
    {
        self::assertSame([], $this->configurationWith([])->getMimeTypes());
    }

    #[Test]
    public function getExcludeDirectoriesSplitsOnSemicolonAndTrims(): void
    {
        $config = $this->configurationWith(['exclude_directories' => '/fileadmin/foo; /fileadmin/bar ;']);

        self::assertSame(['/fileadmin/foo', '/fileadmin/bar'], $config->getExcludeDirectories());
    }

    #[Test]
    public function getExcludeDirectoriesReturnsEmptyArrayWhenKeyMissing(): void
    {
        $config = $this->configurationWith([]);

        self::assertSame([], $config->getExcludeDirectories());
    }

    #[Test]
    public function getFilterPatternReturnsValidPattern(): void
    {
        $config = $this->configurationWith(['filter_pattern' => '/\\.(jpe?g|png|gif)\\.webp$/i']);

        self::assertSame('/\\.(jpe?g|png|gif)\\.webp$/i', $config->getFilterPattern());
    }

    #[Test]
    public function getFilterPatternReturnsNullForInvalidRegex(): void
    {
        $config = $this->configurationWith(['filter_pattern' => '/[invalid/']);

        self::assertNull($config->getFilterPattern());
    }

    #[Test]
    public function getFilterPatternReturnsNullForEmptyString(): void
    {
        $config = $this->configurationWith(['filter_pattern' => '']);

        self::assertNull($config->getFilterPattern());
    }

    #[Test]
    public function accessorsReturnDefaultsWhenExtensionConfigurationThrows(): void
    {
        $extConfig = $this->createMock(ExtensionConfiguration::class);
        $extConfig->method('get')
            ->with('webp')
            ->willThrowException(new ExtensionConfigurationExtensionNotConfiguredException('not configured'));
        $config = new Configuration($extConfig);

        self::assertSame('', $config->getConverter());
        self::assertSame('', $config->getParameters());
        self::assertFalse($config->isSupportedMimeType('image/jpeg'));
        self::assertFalse($config->isConvertAll());
        self::assertSame([], $config->getExcludeDirectories());
        self::assertFalse($config->isSilent());
        self::assertFalse($config->isUseSystemSettings());
        self::assertFalse($config->isHideSiblings());
        self::assertNull($config->getFilterPattern());
    }

    #[Test]
    public function getEnabledFormatsDefaultsToWebpWhenKeyMissing(): void
    {
        self::assertSame(
            [OutputFormat::Webp],
            $this->configurationWith([])->getEnabledFormats(),
        );
    }

    #[Test]
    public function getEnabledFormatsParsesCommaSeparatedListInDeclarationOrder(): void
    {
        self::assertSame(
            [OutputFormat::Webp, OutputFormat::Avif, OutputFormat::Jxl],
            $this->configurationWith(['formats_enabled' => 'webp,avif,jxl'])->getEnabledFormats(),
        );
    }

    #[Test]
    public function getEnabledFormatsSilentlySkipsUnknownTokens(): void
    {
        self::assertSame(
            [OutputFormat::Avif],
            $this->configurationWith(['formats_enabled' => 'avif,heic,unknown'])->getEnabledFormats(),
        );
    }

    #[Test]
    public function getConverterForWebpFallsBackToLegacyKey(): void
    {
        $config = $this->configurationWith([
            'converter' => 'Plan2net\\Webp\\Converter\\VipsConverter',
        ]);

        self::assertSame('Plan2net\\Webp\\Converter\\VipsConverter', $config->getConverterFor(OutputFormat::Webp));
    }

    #[Test]
    public function getConverterForWebpPrefersPerFormatKeyOverLegacy(): void
    {
        $config = $this->configurationWith([
            'converter' => 'Plan2net\\Webp\\Converter\\PhpGdConverter',
            'converter_webp' => 'Plan2net\\Webp\\Converter\\VipsConverter',
        ]);

        self::assertSame('Plan2net\\Webp\\Converter\\VipsConverter', $config->getConverterFor(OutputFormat::Webp));
    }

    #[Test]
    public function getConverterForAvifReturnsEmptyStringWhenUnset(): void
    {
        self::assertSame('', $this->configurationWith([])->getConverterFor(OutputFormat::Avif));
    }

    #[Test]
    public function getParametersForWebpFallsBackToLegacyKey(): void
    {
        $config = $this->configurationWith(['parameters' => 'image/jpeg::Q=85']);

        self::assertSame('Q=85', $config->getParametersFor(OutputFormat::Webp, 'image/jpeg'));
    }

    #[Test]
    public function getParametersForAvifIsNullWhenUnset(): void
    {
        self::assertNull($this->configurationWith([])->getParametersFor(OutputFormat::Avif, 'image/jpeg'));
    }

    #[Test]
    public function getParametersForAvifResolvesPerFormatMimeMap(): void
    {
        $config = $this->configurationWith([
            'parameters_avif' => 'image/jpeg::Q=60 effort=4|image/png::Q=60 effort=4',
        ]);

        self::assertSame('Q=60 effort=4', $config->getParametersFor(OutputFormat::Avif, 'image/png'));
        self::assertNull($config->getParametersFor(OutputFormat::Avif, 'image/gif'));
    }

    #[Test]
    public function isSupportedMimeTypeForAvifReadsPerFormatList(): void
    {
        $config = $this->configurationWith(['mime_types_avif' => 'image/jpeg,image/png']);

        self::assertTrue($config->isSupportedMimeTypeFor(OutputFormat::Avif, 'image/jpeg'));
        self::assertFalse($config->isSupportedMimeTypeFor(OutputFormat::Avif, 'image/gif'));
    }

    #[Test]
    public function isSupportedMimeTypeForWebpFallsBackToLegacyKey(): void
    {
        $config = $this->configurationWith(['mime_types' => 'image/jpeg']);

        self::assertTrue($config->isSupportedMimeTypeFor(OutputFormat::Webp, 'image/jpeg'));
        self::assertFalse($config->isSupportedMimeTypeFor(OutputFormat::Webp, 'image/png'));
    }

    #[Test]
    public function getRawParametersForWebpFallsBackToLegacyKey(): void
    {
        $config = $this->configurationWith(['parameters' => 'image/jpeg::Q=85']);

        self::assertSame('image/jpeg::Q=85', $config->getRawParameters(OutputFormat::Webp));
    }

    #[Test]
    public function getRawParametersForAvifReturnsEmptyStringWhenUnset(): void
    {
        self::assertSame('', $this->configurationWith([])->getRawParameters(OutputFormat::Avif));
    }

    private function configurationWith(array $settings): Configuration
    {
        $extConfig = $this->createMock(ExtensionConfiguration::class);
        $extConfig->method('get')->with('webp')->willReturn($settings);

        return new Configuration($extConfig);
    }
}
