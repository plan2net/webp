<?php

declare(strict_types=1);

namespace Plan2net\Webp\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Plan2net\Webp\Service\PathMatcher;

final class PathMatcherTest extends TestCase
{
    private PathMatcher $pathMatcher;

    protected function setUp(): void
    {
        $this->pathMatcher = new PathMatcher();
    }

    #[Test]
    #[DataProvider('matchesProvider')]
    public function testMatches(string $path, string $prefix, bool $expected, string $description): void
    {
        $result = $this->pathMatcher->matches($path, $prefix);

        self::assertSame($expected, $result, $description);
    }

    public static function matchesProvider(): array
    {
        return [
            'exact match' => [
                'fileadmin/processed',
                'fileadmin/processed',
                true,
                'Should match when paths are identical'
            ],
            'path within directory' => [
                'fileadmin/processed/image.jpg',
                'fileadmin/processed',
                true,
                'Should match when path is within directory'
            ],
            'deep nested path' => [
                'fileadmin/processed/subfolder/deep/image.jpg',
                'fileadmin/processed',
                true,
                'Should match deeply nested paths'
            ],
            'different path' => [
                'fileadmin/other/image.jpg',
                'fileadmin/processed',
                false,
                'Should not match different paths'
            ],
            'partial name match avoided' => [
                'fileadmin/processed_extra/image.jpg',
                'fileadmin/processed',
                false,
                'Should not match partial directory names'
            ],
            'prefix with leading slash' => [
                'fileadmin/processed/image.jpg',
                '/fileadmin/processed',
                true,
                'Should normalize and match with leading slash'
            ],
            'both with leading slashes' => [
                '/fileadmin/processed/image.jpg',
                '/fileadmin/processed',
                true,
                'Should normalize and match when both have leading slashes'
            ],
            'trailing slashes ignored' => [
                'fileadmin/processed/image.jpg',
                'fileadmin/processed/',
                true,
                'Should normalize and match with trailing slash'
            ],
        ];
    }

    #[Test]
    #[DataProvider('matchesAnyProvider')]
    public function testMatchesAny(string $path, array $patterns, bool $expected, string $description): void
    {
        $result = $this->pathMatcher->matchesAny($path, $patterns);

        self::assertSame($expected, $result, $description);
    }

    public static function matchesAnyProvider(): array
    {
        return [
            'matches first pattern' => [
                'fileadmin/excluded/image.jpg',
                ['fileadmin/excluded', 'fileadmin/other'],
                true,
                'Should match when first pattern matches'
            ],
            'matches second pattern' => [
                'fileadmin/special/image.jpg',
                ['fileadmin/other', 'fileadmin/special'],
                true,
                'Should match when second pattern matches'
            ],
            'no match' => [
                'fileadmin/allowed/image.jpg',
                ['fileadmin/excluded', 'fileadmin/special'],
                false,
                'Should not match when no patterns match'
            ],
            'empty patterns' => [
                'any/path/file.jpg',
                [],
                false,
                'Should return false for empty patterns array'
            ],
        ];
    }

    #[Test]
    #[DataProvider('pathsWithSlashVariationsProvider')]
    public function pathIsExcludedRegardlessOfSlashVariations(
        string $filePath,
        string $excludedPath,
        bool $expectedResult,
        string $description
    ): void {
        $result = $this->pathMatcher->matchesAny($filePath, [$excludedPath]);

        self::assertSame($expectedResult, $result, $description);
    }

    public static function pathsWithSlashVariationsProvider(): array
    {
        return [
            'exact match - no slashes' => [
                'fileadmin/user_upload/OpenGraph/image.jpg',
                'fileadmin/user_upload/OpenGraph',
                true,
                'Should match when paths have no leading slashes'
            ],
            'excluded path with leading slash' => [
                'fileadmin/user_upload/OpenGraph/image.jpg',
                '/fileadmin/user_upload/OpenGraph',
                true,
                'Should match when excluded path has leading slash (GitHub issue #112)'
            ],
            'file path with leading slash' => [
                '/fileadmin/user_upload/OpenGraph/image.jpg',
                'fileadmin/user_upload/OpenGraph',
                true,
                'Should match when file path has leading slash'
            ],
            'both with leading slashes' => [
                '/fileadmin/user_upload/OpenGraph/image.jpg',
                '/fileadmin/user_upload/OpenGraph',
                true,
                'Should match when both paths have leading slashes'
            ],
            'excluded path with trailing slash' => [
                'fileadmin/user_upload/OpenGraph/image.jpg',
                'fileadmin/user_upload/OpenGraph/',
                true,
                'Should match when excluded path has trailing slash'
            ],
            'subdirectory match' => [
                'fileadmin/user_upload/OpenGraph/subfolder/deep/image.jpg',
                'fileadmin/user_upload/OpenGraph',
                true,
                'Should match files in subdirectories'
            ],
            'different path' => [
                'fileadmin/user_upload/Other/image.jpg',
                'fileadmin/user_upload/OpenGraph',
                false,
                'Should not match different paths'
            ],
            'partial path match avoided' => [
                'fileadmin/user_upload/OpenGraphExtended/image.jpg',
                'fileadmin/user_upload/OpenGraph',
                false,
                'Should not match partial directory names'
            ],
        ];
    }

    #[Test]
    public function multipleExcludedPathsAreChecked(): void
    {
        $filePath = 'fileadmin/user_upload/Special/image.jpg';
        $excludedPaths = [
            '/fileadmin/user_upload/OpenGraph',
            'fileadmin/user_upload/Special',  // This one should match
            '/fileadmin/user_upload/Other'
        ];

        $result = $this->pathMatcher->matchesAny($filePath, $excludedPaths);

        self::assertTrue($result, 'Should match when file is in one of multiple excluded paths');
    }

    #[Test]
    public function emptyExcludedPathsReturnsFalse(): void
    {
        $result = $this->pathMatcher->matchesAny('any/path/file.jpg', []);

        self::assertFalse($result, 'Should return false when no excluded paths are configured');
    }

    #[Test]
    public function pathMatchingIsCaseSensitive(): void
    {
        $result = $this->pathMatcher->matchesAny(
            'fileadmin/user_upload/opengraph/image.jpg',
            ['fileadmin/user_upload/OpenGraph']
        );

        self::assertFalse($result, 'Path matching should be case-sensitive');
    }

    #[Test]
    public function whitespaceInExcludedPathsIsTrimmed(): void
    {
        $result = $this->pathMatcher->matchesAny(
            'fileadmin/user_upload/OpenGraph/image.jpg',
            ['  /fileadmin/user_upload/OpenGraph  ']
        );

        self::assertTrue($result, 'Should trim whitespace from excluded paths');
    }
}
