<?php

declare(strict_types=1);

namespace Plan2net\Webp\Webserver;

use Plan2net\Webp\Format\OutputFormat;

final class RewriteConfigGenerator
{
    /**
     * @param list<OutputFormat> $formatsInPriorityOrder
     * @param list<string>       $sourceExtensions
     *
     * @return array<string, string> scope key => fragment (functional guidance only; the command adds placement headers)
     */
    public function generate(WebserverType $server, array $formatsInPriorityOrder, array $sourceExtensions): array
    {
        $extensions = \implode('|', $sourceExtensions);

        return match ($server) {
            WebserverType::Nginx => $this->nginx($formatsInPriorityOrder, $extensions),
            WebserverType::Apache => ['main' => $this->apache($formatsInPriorityOrder, $extensions)],
            WebserverType::Caddy => ['main' => $this->caddy($formatsInPriorityOrder, $extensions)],
        };
    }

    /**
     * @param list<OutputFormat> $formats
     *
     * @return array{http: string, server: string}
     */
    private function nginx(array $formats, string $extensions): array
    {
        $mapLines = ['    default "";'];
        foreach ($formats as $format) {
            $mapLines[] = \sprintf('    "~*%s" "%s";', $format->mimeType(), $format->suffix());
        }

        $http = "# Accept header to sibling suffix, preference order AVIF > WebP > JXL (first match wins).\n"
            . "map \$http_accept \$sibling_suffix {\n"
            . \implode("\n", $mapLines) . "\n"
            . "}\n";

        $server = "# Keep this above any generic static-asset location.\n"
            . "# Behind a CDN such as Cloudflare, or to restrict by user agent, see the README.\n"
            . \sprintf("location ~* ^.+\\.(%s)$ {\n", $extensions)
            . "    add_header Vary \"Accept\";\n"
            . "    add_header Cache-Control \"public, no-transform\";\n"
            . "    try_files \$uri\$sibling_suffix \$uri =404;\n"
            . "}\n";

        return ['http' => $http, 'server' => $server];
    }

    /**
     * @param list<OutputFormat> $formats
     */
    private function apache(array $formats, string $extensions): string
    {
        $addTypes = '';
        $rewrites = '';
        foreach ($formats as $format) {
            $addTypes .= \sprintf("AddType %s %s\n", $format->mimeType(), $format->suffix());
            $rewrites .= \sprintf("RewriteCond %%{HTTP_ACCEPT} %s\n", $format->mimeType())
                . \sprintf("RewriteCond %%{REQUEST_FILENAME} (.*)\\.(?i:%s)$\n", $extensions)
                . \sprintf("RewriteCond %%{REQUEST_FILENAME}\\%s -f\n", $format->suffix())
                . \sprintf("RewriteRule ^ %%{REQUEST_FILENAME}\\%s [L,T=%s]\n\n", $format->suffix(), $format->mimeType());
        }

        return "# Paste inside the site <Directory> or into .htaccess.\n"
            . "# On shared hosting (e.g. IONOS) where %{REQUEST_FILENAME} does not resolve, replace it\n"
            . "# with %{REQUEST_URI}; to restrict by user agent, see the README.\n"
            . $addTypes
            . "RewriteEngine On\n\n"
            . "# Preference order AVIF > WebP > JXL (first matching rule wins).\n"
            . $rewrites
            . "<IfModule mod_headers.c>\n"
            . \sprintf("    <FilesMatch \"\\.(%s)$\">\n", $extensions)
            . "        Header append Vary Accept\n"
            . "    </FilesMatch>\n"
            . "</IfModule>\n";
    }

    /**
     * @param list<OutputFormat> $formats
     */
    private function caddy(array $formats, string $extensions): string
    {
        $maps = '';
        $tryFiles = 'try_files';
        $matchers = '';
        $headers = '';
        foreach ($formats as $format) {
            $name = $format->value . '_suffix';
            $maps .= \sprintf("map {header.Accept} {%s} {\n    ~%s %s\n    default \"\"\n}\n", $name, $format->mimeType(), $format->value);
            $tryFiles .= \sprintf(' {path}.{%s}', $name);
            $matchers .= \sprintf("        @served_%s path *%s\n", $format->value, $format->suffix());
            $headers .= \sprintf("        header @served_%s Content-Type %s\n", $format->value, $format->mimeType());
        }
        $tryFiles .= ' {path}';

        return "# Paste inside your site block (a file_server and root must already be configured).\n"
            . "# Accept header to sibling suffix, preference order AVIF > WebP > JXL. To restrict by user agent, see the README.\n"
            . $maps
            . \sprintf("@images path_regexp \\.(%s)$\n", $extensions)
            . "handle @images {\n"
            . "    header Vary Accept\n"
            . "    route {\n"
            . '        ' . $tryFiles . "\n"
            . $matchers
            . $headers
            . "        file_server\n"
            . "    }\n"
            . "}\n";
    }
}
