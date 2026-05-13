<?php

declare(strict_types=1);

namespace Plan2net\Webp\Tests\Unit\Core\Filter;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Plan2net\Webp\Core\Filter\FileNameFilter;
use Plan2net\Webp\Service\Configuration;

final class FileNameFilterTest extends TestCase
{
    #[Test]
    public function filterReturnsNegativeOneForJpegWebpSibling(): void
    {
        $this->seedPattern('/\\.(jpe?g|png|gif)\\.webp$/i');

        self::assertSame(-1, FileNameFilter::filterWebpFiles('photo.jpg.webp', '/_processed_/photo.jpg.webp'));
    }

    #[Test]
    public function filterReturnsNegativeOneForPngWebpSibling(): void
    {
        $this->seedPattern('/\\.(jpe?g|png|gif)\\.webp$/i');

        self::assertSame(-1, FileNameFilter::filterWebpFiles('icon.png.webp', '/_processed_/icon.png.webp'));
    }

    #[Test]
    public function filterReturnsNegativeOneForGifWebpSibling(): void
    {
        $this->seedPattern('/\\.(jpe?g|png|gif)\\.webp$/i');

        self::assertSame(-1, FileNameFilter::filterWebpFiles('banner.gif.webp', '/_processed_/banner.gif.webp'));
    }

    #[Test]
    public function filterReturnsOneForRegularImage(): void
    {
        $this->seedPattern('/\\.(jpe?g|png|gif)\\.webp$/i');

        self::assertSame(1, FileNameFilter::filterWebpFiles('photo.jpg', '/_processed_/photo.jpg'));
    }

    #[Test]
    public function filterReturnsOneForStandaloneWebp(): void
    {
        $this->seedPattern('/\\.(jpe?g|png|gif)\\.webp$/i');

        self::assertSame(1, FileNameFilter::filterWebpFiles('pure.webp', '/upload/pure.webp'));
    }

    #[Test]
    #[DataProvider('invalidOrEmptyPatternProvider')]
    public function filterReturnsOneWhenPatternIsEmptyOrInvalid(string $pattern): void
    {
        $this->seedPattern($pattern);

        self::assertSame(1, FileNameFilter::filterWebpFiles('photo.jpg.webp', '/_processed_/photo.jpg.webp'));
    }

    public static function invalidOrEmptyPatternProvider(): array
    {
        return [
            'empty pattern' => [''],
            'invalid regex' => ['/[invalid/'],
        ];
    }

    protected function setUp(): void
    {
        $this->resetConfigurationCache();
    }

    protected function tearDown(): void
    {
        $this->resetConfigurationCache();
    }

    /**
     * HACK: Service\Configuration uses a private static cache populated lazily
     * from ExtensionConfiguration. Reflection is the only way to seed it without
     * bootstrapping TYPO3. Remove once Configuration's static state is refactored.
     */
    private function seedPattern(string $pattern): void
    {
        $property = (new \ReflectionClass(Configuration::class))->getProperty('configuration');
        $property->setValue(null, ['filter_pattern' => $pattern]);
    }

    private function resetConfigurationCache(): void
    {
        $property = (new \ReflectionClass(Configuration::class))->getProperty('configuration');
        $property->setValue(null, []);
    }
}
