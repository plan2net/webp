<?php

declare(strict_types=1);

namespace Plan2net\Webp\Service;

final class CompressionReport
{
    /**
     * @param list<array{identifier: string, size: int, width: int, height: int, format: string|null}> $rows
     *
     * @return list<array{label: string, sourceSize: int, results: array<string, array{size: int, savingsPercent: int}>}>
     */
    public static function build(array $rows, int $originalSize, string $originalIdentifier): array
    {
        $bases = [];
        foreach ($rows as $row) {
            if (null === $row['format']) {
                $bases[$row['identifier']] = $row;
            }
        }

        $variants = [];
        foreach ($rows as $row) {
            $format = $row['format'];
            if (null === $format) {
                continue;
            }

            $suffix = '.' . $format;
            $baseIdentifier = \str_ends_with($row['identifier'], $suffix)
                ? \substr($row['identifier'], 0, -\strlen($suffix))
                : $row['identifier'];

            if (isset($bases[$baseIdentifier])) {
                $sourceSize = (int) $bases[$baseIdentifier]['size'];
                $width = (int) $bases[$baseIdentifier]['width'];
                $height = (int) $bases[$baseIdentifier]['height'];
            } elseif ('' !== $originalIdentifier && $baseIdentifier === $originalIdentifier) {
                $sourceSize = $originalSize;
                $width = (int) $row['width'];
                $height = (int) $row['height'];
            } else {
                continue;
            }

            $variants[$baseIdentifier] ??= [
                'label' => $width > 0 && $height > 0 ? $width . '×' . $height : $baseIdentifier,
                'sourceSize' => $sourceSize,
                'results' => [],
            ];

            $siblingSize = (int) $row['size'];
            $variants[$baseIdentifier]['results'][$format] = [
                'size' => $siblingSize,
                'savingsPercent' => $sourceSize > 0
                    ? (int) \round(($sourceSize - $siblingSize) / $sourceSize * 100)
                    : 0,
            ];
        }

        $list = \array_values($variants);
        \usort($list, static fn (array $a, array $b): int => $b['sourceSize'] <=> $a['sourceSize']);

        return $list;
    }
}
