<?php

declare(strict_types=1);

namespace Plan2net\Webp\Tests\Unit\Core\Filter;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Plan2net\Webp\Core\Filter\FileNameFilter;
use Plan2net\Webp\Service\Configuration;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class FileNameFilterTest extends TestCase
{
    private MockObject $extensionConfiguration;

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
        $this->extensionConfiguration = $this->createMock(ExtensionConfiguration::class);
        // One Configuration instance per test — filterWebpFiles() consumes it on
        // its first GeneralUtility::makeInstance call. Each #[Test] body in this
        // class calls filterWebpFiles exactly once, so one addInstance suffices.
        GeneralUtility::addInstance(
            Configuration::class,
            new Configuration($this->extensionConfiguration),
        );
    }

    protected function tearDown(): void
    {
        GeneralUtility::purgeInstances();
    }

    private function seedPattern(string $pattern): void
    {
        $this->extensionConfiguration->method('get')
            ->with('webp')
            ->willReturn(['filter_pattern' => $pattern]);
    }
}
