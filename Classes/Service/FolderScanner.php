<?php

declare(strict_types=1);

namespace Plan2net\Webp\Service;

use Plan2net\Webp\Format\OutputFormat;
use Plan2net\Webp\Format\SourceMimeType;
use Symfony\Component\Finder\Finder;

final class FolderScanner
{
    /**
     * Walks the folder and yields convertible images that are still missing at
     * least one of the requested sibling formats. A file with every requested
     * format already on disk is skipped.
     *
     * @param list<string>       $allowedMimeTypes
     * @param list<OutputFormat> $expectedFormats  sibling formats the caller plans to generate
     *
     * @return \Generator<array{path: string, mimeType: string, missingFormats: list<OutputFormat>}>
     */
    public function scan(string $folder, array $allowedMimeTypes, array $expectedFormats): \Generator
    {
        if (!\is_dir($folder) || [] === $expectedFormats) {
            return;
        }
        $extensions = SourceMimeType::extensionsAllowedBy($allowedMimeTypes);
        if ([] === $extensions) {
            return;
        }

        $extensionPattern = \implode('|', \array_map(
            static fn (string $extension): string => \preg_quote($extension, '/'),
            $extensions,
        ));
        $finder = (new Finder())
            ->files()
            ->in($folder)
            ->name('/\\.(?:' . $extensionPattern . ')$/i');

        foreach ($finder as $fileInfo) {
            $path = $fileInfo->getPathname();
            $missing = [];
            foreach ($expectedFormats as $format) {
                if (!\is_file($path . $format->suffix())) {
                    $missing[] = $format;
                }
            }
            if ([] === $missing) {
                continue;
            }
            $mimeType = SourceMimeType::fromExtension($fileInfo->getExtension());
            if (null === $mimeType) {
                continue;
            }
            yield ['path' => $path, 'mimeType' => $mimeType, 'missingFormats' => $missing];
        }
    }
}
