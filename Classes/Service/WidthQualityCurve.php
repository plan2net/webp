<?php

declare(strict_types=1);

namespace Plan2net\Webp\Service;

final class WidthQualityCurve
{
    /**
     * @return list<array{maxWidth: int, quality: int}>
     */
    public static function parse(string $curve): array
    {
        $bands = [];
        foreach (\explode('|', $curve) as $segment) {
            $parts = \explode(':', \trim($segment), 2);
            if (2 !== \count($parts)) {
                continue;
            }
            $maxWidth = \filter_var(\trim($parts[0]), \FILTER_VALIDATE_INT);
            $quality = \filter_var(\trim($parts[1]), \FILTER_VALIDATE_INT);
            if (false === $maxWidth || false === $quality || $maxWidth < 1 || $quality < 1 || $quality > 100) {
                continue;
            }
            $bands[] = ['maxWidth' => $maxWidth, 'quality' => $quality];
        }

        \usort($bands, static fn (array $a, array $b): int => $a['maxWidth'] <=> $b['maxWidth']);

        return $bands;
    }

    /**
     * @param list<array{maxWidth: int, quality: int}> $bands
     */
    public static function qualityForWidth(array $bands, int $width): ?int
    {
        if ([] === $bands) {
            return null;
        }

        foreach ($bands as $band) {
            if ($width <= $band['maxWidth']) {
                return $band['quality'];
            }
        }

        return $bands[\array_key_last($bands)]['quality'];
    }
}
