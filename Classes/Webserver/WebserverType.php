<?php

declare(strict_types=1);

namespace Plan2net\Webp\Webserver;

enum WebserverType: string
{
    case Nginx = 'nginx';
    case Apache = 'apache';
    case Caddy = 'caddy';

    /**
     * @return list<string>
     */
    public function scopeKeys(): array
    {
        return match ($this) {
            self::Nginx => ['http', 'server'],
            self::Apache, self::Caddy => ['main'],
        };
    }
}
