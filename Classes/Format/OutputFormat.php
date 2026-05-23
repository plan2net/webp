<?php

declare(strict_types=1);

namespace Plan2net\Webp\Format;

enum OutputFormat: string
{
    case Webp = 'webp';
    case Avif = 'avif';
    case Jxl = 'jxl';

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
}
