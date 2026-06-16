<?php

declare(strict_types=1);

namespace Plan2net\Webp\Tests\Unit\Webserver;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Plan2net\Webp\Webserver\WebserverType;

final class WebserverTypeTest extends TestCase
{
    #[Test]
    public function scopeKeysAreServerSpecific(): void
    {
        self::assertSame(['http', 'server'], WebserverType::Nginx->scopeKeys());
        self::assertSame(['main'], WebserverType::Apache->scopeKeys());
        self::assertSame(['main'], WebserverType::Caddy->scopeKeys());
    }

    #[Test]
    public function valuesMatchTheCliChoices(): void
    {
        self::assertSame('nginx', WebserverType::Nginx->value);
        self::assertSame('apache', WebserverType::Apache->value);
        self::assertSame('caddy', WebserverType::Caddy->value);
    }
}
