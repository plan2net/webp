<?php

declare(strict_types=1);

namespace Plan2net\Webp\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Plan2net\Webp\Service\FolderScanner;

final class FolderScannerTest extends TestCase
{
    private string $tempDir;

    #[Test]
    public function yieldsImagesWithoutSiblingTaggedWithMimeType(): void
    {
        $this->touchAtPath($this->tempDir . '/foo.png');
        $this->touchAtPath($this->tempDir . '/bar.jpg');
        $this->touchAtPath($this->tempDir . '/baz.png');
        $this->touchAtPath($this->tempDir . '/baz.png.webp');

        $entries = \iterator_to_array(
            (new FolderScanner())->scan($this->tempDir, ['image/jpeg', 'image/png']),
            false
        );

        \usort($entries, static fn (array $a, array $b): int => $a['path'] <=> $b['path']);
        self::assertSame(
            [
                ['path' => $this->tempDir . '/bar.jpg', 'mimeType' => 'image/jpeg'],
                ['path' => $this->tempDir . '/foo.png', 'mimeType' => 'image/png'],
            ],
            $entries
        );
    }

    #[Test]
    public function filtersByMimeType(): void
    {
        $this->touchAtPath($this->tempDir . '/foo.png');
        $this->touchAtPath($this->tempDir . '/bar.gif');

        $entries = \iterator_to_array(
            (new FolderScanner())->scan($this->tempDir, ['image/png']),
            false
        );

        self::assertSame(
            [['path' => $this->tempDir . '/foo.png', 'mimeType' => 'image/png']],
            $entries
        );
    }

    #[Test]
    public function recursesIntoSubdirectories(): void
    {
        \mkdir($this->tempDir . '/sub', 0o777, true);
        $this->touchAtPath($this->tempDir . '/sub/deep.png');

        $entries = \iterator_to_array(
            (new FolderScanner())->scan($this->tempDir, ['image/png']),
            false
        );

        self::assertSame(
            [['path' => $this->tempDir . '/sub/deep.png', 'mimeType' => 'image/png']],
            $entries
        );
    }

    #[Test]
    public function returnsEmptyForMissingFolder(): void
    {
        $entries = \iterator_to_array(
            (new FolderScanner())->scan('/nonexistent/path', ['image/png']),
            false
        );

        self::assertSame([], $entries);
    }

    #[Test]
    public function ignoresUnknownMimeTypes(): void
    {
        $this->touchAtPath($this->tempDir . '/photo.png');

        $entries = \iterator_to_array(
            (new FolderScanner())->scan($this->tempDir, ['image/avif']),
            false
        );

        self::assertSame([], $entries);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = \sys_get_temp_dir() . '/folder-scanner-' . \bin2hex(\random_bytes(6));
        \mkdir($this->tempDir, 0o777, true);
    }

    protected function tearDown(): void
    {
        $this->removeRecursive($this->tempDir);
        parent::tearDown();
    }

    private function touchAtPath(string $path): void
    {
        $dir = \dirname($path);
        if (!\is_dir($dir)) {
            \mkdir($dir, 0o777, true);
        }
        \file_put_contents($path, 'fake');
    }

    private function removeRecursive(string $path): void
    {
        if (!\file_exists($path)) {
            return;
        }
        if (\is_dir($path)) {
            foreach (\scandir($path) ?: [] as $entry) {
                if ('.' === $entry || '..' === $entry) {
                    continue;
                }
                $this->removeRecursive($path . '/' . $entry);
            }
            \rmdir($path);

            return;
        }
        \unlink($path);
    }
}
