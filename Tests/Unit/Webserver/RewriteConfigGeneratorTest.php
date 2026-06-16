<?php

declare(strict_types=1);

namespace Plan2net\Webp\Tests\Unit\Webserver;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Plan2net\Webp\Format\OutputFormat;
use Plan2net\Webp\Webserver\RewriteConfigGenerator;
use Plan2net\Webp\Webserver\WebserverType;

final class RewriteConfigGeneratorTest extends TestCase
{
    private const ALL = [OutputFormat::Avif, OutputFormat::Webp, OutputFormat::Jxl];
    private const EXTS = ['jpg', 'jpeg', 'png', 'gif'];

    #[Test]
    public function nginxHttpScopeMatchesGolden(): void
    {
        $expected = <<<'NGINX'
            # Accept header to sibling suffix, preference order AVIF > WebP > JXL (first match wins).
            map $http_accept $sibling_suffix {
                default "";
                "~*image/avif" ".avif";
                "~*image/webp" ".webp";
                "~*image/jxl" ".jxl";
            }

            NGINX;

        self::assertSame($expected, (new RewriteConfigGenerator())->generate(WebserverType::Nginx, self::ALL, self::EXTS)['http']);
    }

    #[Test]
    public function nginxServerScopeMatchesGolden(): void
    {
        $expected = <<<'NGINX'
            # Keep this above any generic static-asset location.
            # Behind a CDN such as Cloudflare, or to restrict by user agent, see the README.
            location ~* ^.+\.(jpg|jpeg|png|gif)$ {
                add_header Vary "Accept";
                add_header Cache-Control "public, no-transform";
                try_files $uri$sibling_suffix $uri =404;
            }

            NGINX;

        self::assertSame($expected, (new RewriteConfigGenerator())->generate(WebserverType::Nginx, self::ALL, self::EXTS)['server']);
    }

    #[Test]
    public function apacheMainScopeMatchesGolden(): void
    {
        $expected = <<<'APACHE'
            # Paste inside the site <Directory> or into .htaccess.
            # On shared hosting (e.g. IONOS) where %{REQUEST_FILENAME} does not resolve, replace it
            # with %{REQUEST_URI}; to restrict by user agent, see the README.
            AddType image/avif .avif
            AddType image/webp .webp
            AddType image/jxl .jxl
            RewriteEngine On

            # Preference order AVIF > WebP > JXL (first matching rule wins).
            RewriteCond %{HTTP_ACCEPT} image/avif
            RewriteCond %{REQUEST_FILENAME} (.*)\.(?i:jpg|jpeg|png|gif)$
            RewriteCond %{REQUEST_FILENAME}\.avif -f
            RewriteRule ^ %{REQUEST_FILENAME}\.avif [L,T=image/avif]

            RewriteCond %{HTTP_ACCEPT} image/webp
            RewriteCond %{REQUEST_FILENAME} (.*)\.(?i:jpg|jpeg|png|gif)$
            RewriteCond %{REQUEST_FILENAME}\.webp -f
            RewriteRule ^ %{REQUEST_FILENAME}\.webp [L,T=image/webp]

            RewriteCond %{HTTP_ACCEPT} image/jxl
            RewriteCond %{REQUEST_FILENAME} (.*)\.(?i:jpg|jpeg|png|gif)$
            RewriteCond %{REQUEST_FILENAME}\.jxl -f
            RewriteRule ^ %{REQUEST_FILENAME}\.jxl [L,T=image/jxl]

            <IfModule mod_headers.c>
                <FilesMatch "\.(jpg|jpeg|png|gif)$">
                    Header append Vary Accept
                </FilesMatch>
            </IfModule>

            APACHE;

        self::assertSame($expected, (new RewriteConfigGenerator())->generate(WebserverType::Apache, self::ALL, self::EXTS)['main']);
    }

    #[Test]
    public function caddyMainScopeMatchesGolden(): void
    {
        $expected = <<<'CADDY'
            # Paste inside your site block (a file_server and root must already be configured).
            # Accept header to sibling suffix, preference order AVIF > WebP > JXL. To restrict by user agent, see the README.
            map {header.Accept} {avif_suffix} {
                ~image/avif avif
                default ""
            }
            map {header.Accept} {webp_suffix} {
                ~image/webp webp
                default ""
            }
            map {header.Accept} {jxl_suffix} {
                ~image/jxl jxl
                default ""
            }
            @images path_regexp \.(jpg|jpeg|png|gif)$
            handle @images {
                header Vary Accept
                route {
                    try_files {path}.{avif_suffix} {path}.{webp_suffix} {path}.{jxl_suffix} {path}
                    @served_avif path *.avif
                    @served_webp path *.webp
                    @served_jxl path *.jxl
                    header @served_avif Content-Type image/avif
                    header @served_webp Content-Type image/webp
                    header @served_jxl Content-Type image/jxl
                    file_server
                }
            }

            CADDY;

        self::assertSame($expected, (new RewriteConfigGenerator())->generate(WebserverType::Caddy, self::ALL, self::EXTS)['main']);
    }

    #[Test]
    public function webpOnlyNginxHttpOmitsOtherFormats(): void
    {
        $expected = <<<'NGINX'
            # Accept header to sibling suffix, preference order AVIF > WebP > JXL (first match wins).
            map $http_accept $sibling_suffix {
                default "";
                "~*image/webp" ".webp";
            }

            NGINX;

        self::assertSame($expected, (new RewriteConfigGenerator())->generate(WebserverType::Nginx, [OutputFormat::Webp], self::EXTS)['http']);
    }

    #[Test]
    public function nginxHttpPreservesInputFormatOrder(): void
    {
        $expected = <<<'NGINX'
            # Accept header to sibling suffix, preference order AVIF > WebP > JXL (first match wins).
            map $http_accept $sibling_suffix {
                default "";
                "~*image/webp" ".webp";
                "~*image/avif" ".avif";
            }

            NGINX;

        self::assertSame($expected, (new RewriteConfigGenerator())->generate(WebserverType::Nginx, [OutputFormat::Webp, OutputFormat::Avif], self::EXTS)['http']);
    }
}
