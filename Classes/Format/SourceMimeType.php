<?php

declare(strict_types=1);

namespace Plan2net\Webp\Format;

final class SourceMimeType
{
    private const BY_EXTENSION = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
    ];

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return \array_values(\array_unique(self::BY_EXTENSION));
    }

    public static function fromExtension(string $extension): ?string
    {
        return self::BY_EXTENSION[\strtolower($extension)] ?? null;
    }

    /**
     * @param list<string> $allowedMimeTypes
     *
     * @return list<string> file extensions whose mime type appears in $allowedMimeTypes
     */
    public static function extensionsAllowedBy(array $allowedMimeTypes): array
    {
        $allowed = \array_flip(\array_map('strtolower', $allowedMimeTypes));
        $result = [];
        foreach (self::BY_EXTENSION as $extension => $mime) {
            if (isset($allowed[$mime])) {
                $result[] = $extension;
            }
        }

        return $result;
    }
}
