<?php

declare(strict_types=1);

namespace Plan2net\Webp\Webserver;

enum WebserverType: string
{
    case Nginx = 'nginx';
    case Apache = 'apache';
    case Caddy = 'caddy';
}
