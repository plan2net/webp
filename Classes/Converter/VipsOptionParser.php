<?php

declare(strict_types=1);

namespace Plan2net\Webp\Converter;

final class VipsOptionParser
{
    /**
     * @return array<string, bool|int|float|string>
     */
    public static function parse(string $parameters): array
    {
        $options = [];
        foreach (\preg_split('/\\s+/', $parameters, -1, \PREG_SPLIT_NO_EMPTY) as $token) {
            [$key, $value] = \array_pad(\explode('=', $token, 2), 2, '');
            if ('' === $key || '' === $value) {
                continue;
            }
            $options[$key] = self::coerce($value);
        }

        return $options;
    }

    private static function coerce(string $value): bool|int|float|string
    {
        return match (true) {
            0 === \strcasecmp($value, 'true') => true,
            0 === \strcasecmp($value, 'false') => false,
            1 === \preg_match('/^-?\\d+$/', $value) => (int) $value,
            1 === \preg_match('/^-?\\d+\\.\\d+$/', $value) => (float) $value,
            default => $value,
        };
    }
}
