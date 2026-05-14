<?php

declare(strict_types=1);

namespace Plan2net\Webp\Service;

use Symfony\Component\Finder\Finder;

final class FolderScanner
{
    private const MIME_TYPE_BY_EXTENSION = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
    ];

    /**
     * Walks the folder and yields convertible images that don't yet have a `.webp` sibling.
     *
     * @param list<string> $allowedMimeTypes
     *
     * @return \Generator<array{path: string, mimeType: string}>
     */
    public function scan(string $folder, array $allowedMimeTypes): \Generator
    {
        if (!\is_dir($folder)) {
            return;
        }
        $extensionToMime = $this->extensionToMimeMap($allowedMimeTypes);
        if ([] === $extensionToMime) {
            return;
        }

        $finder = (new Finder())
            ->files()
            ->in($folder)
            ->name(\array_map(static fn (string $ext): string => '*.' . $ext, \array_keys($extensionToMime)));

        foreach ($finder as $fileInfo) {
            $path = $fileInfo->getPathname();
            if (\is_file($path . '.webp')) {
                continue;
            }
            $extension = \strtolower($fileInfo->getExtension());
            $mimeType = $extensionToMime[$extension] ?? null;
            if (null === $mimeType) {
                continue;
            }
            yield ['path' => $path, 'mimeType' => $mimeType];
        }
    }

    /**
     * @param list<string> $mimeTypes
     *
     * @return array<string, string> extension => mime type
     */
    private function extensionToMimeMap(array $mimeTypes): array
    {
        $allowed = \array_flip(\array_map('strtolower', $mimeTypes));
        $map = [];
        foreach (self::MIME_TYPE_BY_EXTENSION as $extension => $mime) {
            if (isset($allowed[$mime])) {
                $map[$extension] = $mime;
            }
        }

        return $map;
    }
}
