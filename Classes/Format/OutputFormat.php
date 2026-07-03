<?php

declare(strict_types=1);

namespace Plan2net\Webp\Format;

enum OutputFormat: string
{
    case Webp = 'webp';
    case Avif = 'avif';
    case Jxl = 'jxl';

    /**
     * @return list<self>
     */
    public static function casesInDeliveryPriority(): array
    {
        return [self::Avif, self::Webp, self::Jxl];
    }

    public function label(): string
    {
        return match ($this) {
            self::Webp => 'WebP',
            self::Avif => 'AVIF',
            self::Jxl => 'JXL',
        };
    }

    public function suffix(): string
    {
        return '.' . $this->value;
    }

    public function mimeType(): string
    {
        return match ($this) {
            self::Webp => 'image/webp',
            self::Avif => 'image/avif',
            self::Jxl => 'image/jxl',
        };
    }

    public static function isOutputExtension(string $extension): bool
    {
        return null !== self::tryFrom(\strtolower($extension));
    }
}
