# Webserver configuration

[← README](../README.md)

> [!TIP]
> Run `./vendor/bin/typo3 webp:webserver-config --server=nginx` (or `apache` / `caddy`). It prints a snippet for **your** enabled formats and source types, with the order AVIF → WebP → JXL already right. The rules on this page are the same output, shown in full for reference, together with the Cloudflare and user agent variants the command points to.

The webserver reads the client's `Accept` header and rewrites the request to the best sibling that exists on disk. With only WebP enabled, the server has two choices: it sends `.webp` when the client accepts it, and the original JPEG, PNG or GIF otherwise. With AVIF or JPEG XL enabled too, the server picks from the available siblings in the order the client prefers.

Paste the output of `webp:webserver-config` into your own server or vhost configuration. The E2E suite puts that output into a scaffold for each server ([nginx.conf](../Tests/E2E/nginx.conf), [apache.conf](../Tests/E2E/apache.conf), [caddy.conf](../Tests/E2E/caddy.conf)) and runs real nginx, Apache and Caddy against it. So the generated rules are tested to negotiate correctly.

## Which blocks do I need?

Write rules **only for the formats your install produces** (the [`formats_enabled`](Configuration.md#formats_enabled) setting), and always list them in this fixed order: **AVIF → WebP → JPEG XL**. The first match wins, and the wrong order is the most common reason why only one format is ever served.

| `formats_enabled` | Keep these rules            |
|-------------------|-----------------------------|
| `webp`            | WebP only                   |
| `webp,avif`       | AVIF + WebP (AVIF first)     |
| `webp,avif,jxl`   | all three, AVIF → WebP → JXL |

Each example below shows all three formats. Delete the blocks for the formats you didn't enable.

> [!IMPORTANT]
> Make sure no earlier rule in your configuration handles the image extensions first (for example a general block for static assets). If one does, move the sibling rules above it or change the earlier rule.

## nginx

Add a `map` directive in the `http` block. The order matters, because the first match wins. List the formats in order of preference: AVIF first, then WebP, then JXL.

```nginx
map $http_accept $sibling_suffix {
    default        "";
    "~*image/avif" ".avif";
    "~*image/webp" ".webp";
    "~*image/jxl"  ".jxl";
}
```

If you only generate `.webp`, the map can be the one-line `"~*webp" ".webp";` variant. Clients never ask for `image/avif` from a server that doesn't offer it.

### Behind Cloudflare

If Cloudflare is in front of the site, use this variant instead. Cloudflare caches one response per URL and would otherwise mix the formats:

```nginx
map $http_accept $sibling_suffix_raw {
    default        "";
    "~*image/avif" ".avif";
    "~*image/webp" ".webp";
    "~*image/jxl"  ".jxl";
}
map $http_cf_cache_status $iscf {
    default   1;
    ""        0;
}
# Suppress the sibling whenever Cloudflare is in front (iscf=1). Composite key
# is "<iscf><suffix>": "0.avif" serves AVIF directly, "1.avif" serves the
# original. Every iscf=1 case falls through `default ""`.
map $iscf$sibling_suffix_raw $sibling_suffix {
    default   "";
    "0.avif"  ".avif";
    "0.webp"  ".webp";
    "0.jxl"   ".jxl";
}
```

### Location block

Add the location block to your `server`:

```nginx
location ~* ^.+\.(png|gif|jpe?g)$ {
    # Replaces the inherited MIME table for this location; stock mime.types
    # lacks image/jxl (and image/avif on older nginx).
    types {
        image/jpeg jpg jpeg;
        image/png png;
        image/gif gif;
        image/avif avif;
        image/webp webp;
        image/jxl jxl;
    }
    add_header Vary "Accept";
    add_header Cache-Control "public, no-transform";
    try_files $uri$sibling_suffix $uri =404;
}
```

> [!IMPORTANT]
> An `add_header` directive in a location drops **all** `add_header` lines inherited from the `server` or `http` level. Repeat your site-wide headers (HSTS etc.) inside the block.

### Restrict by user agent (optional)

```nginx
location ~* ^.+\.(png|gif|jpe?g)$ {
    if ($http_user_agent !~* (Chrome|Firefox|Edge)) {
        set $sibling_suffix "";
    }
    …
}
```

## Apache

TYPO3's default `.htaccess` template (`typo3/sysext/install/Resources/Private/FolderStructureTemplateFiles/root-htaccess`) already has the first two directives. They are shown here so the example is complete. `mod_rewrite.c` must be enabled.

```apache
RewriteEngine On
AddType image/webp .webp
AddType image/avif .avif
AddType image/jxl  .jxl

# AVIF preferred over WebP — order matters, first matching rule wins.
RewriteCond %{HTTP_ACCEPT} image/avif
RewriteCond %{REQUEST_FILENAME} (.*)\.(?i:png|gif|jpe?g)$
RewriteCond %{REQUEST_FILENAME}\.avif -s
RewriteRule ^ %{REQUEST_FILENAME}\.avif [L,T=image/avif]

RewriteCond %{HTTP_ACCEPT} image/webp
RewriteCond %{REQUEST_FILENAME} (.*)\.(?i:png|gif|jpe?g)$
RewriteCond %{REQUEST_FILENAME}\.webp -s
RewriteRule ^ %{REQUEST_FILENAME}\.webp [L,T=image/webp]

RewriteCond %{HTTP_ACCEPT} image/jxl
RewriteCond %{REQUEST_FILENAME} (.*)\.(?i:png|gif|jpe?g)$
RewriteCond %{REQUEST_FILENAME}\.jxl -s
RewriteRule ^ %{REQUEST_FILENAME}\.jxl [L,T=image/jxl]

<IfModule mod_headers.c>
    <FilesMatch "\.(png|gif|jpe?g)$">
        Header append Vary Accept
    </FilesMatch>
</IfModule>
```

If you only generate `.webp` (the default `formats_enabled = webp`), keep only the WebP block and drop the AVIF and JXL blocks.

The rules use `-s` (exists and is not empty) instead of `-f`, so an empty sibling is never served as a blank image.

### When `%{REQUEST_FILENAME}` doesn't resolve

Some environments return 403 or 404 with the `%{REQUEST_FILENAME}` form, because Apache can't map the request to a file path at that point. Examples are shared hosting (IONOS etc.), Apache on Windows, and setups where the rewrite runs before the path is resolved. Two portable alternatives:

```apache
RewriteRule ^ %{REQUEST_URI}.webp [L,T=image/webp]
```

or:

```apache
RewriteRule ^(.*)$ $1.webp [L,T=image/webp]
```

Use either one instead of the `RewriteRule ^ %{REQUEST_FILENAME}\.webp …` line above.

### Restrict by user agent (optional)

```apache
RewriteCond %{HTTP_ACCEPT} image/webp
RewriteCond %{HTTP_USER_AGENT} ^.*(Chrome|Firefox|Edge).*$ [NC]
…
```

## Caddy

Caddy reads the `Accept` header with one `map` per format and a single `try_files` in order of preference. The sibling names match this extension (`photo.jpg.avif`), so the public URL never changes: `try_files` serves the first sibling that exists. Put this inside your site block. It expects a `file_server` (and `root`) that is already configured, as in a standard TYPO3 Caddy vhost:

```caddy
map {header.Accept} {avif_suffix} {
    ~image/avif avif
    default     ""
}
map {header.Accept} {webp_suffix} {
    ~image/webp webp
    default     ""
}
map {header.Accept} {jxl_suffix} {
    ~image/jxl  jxl
    default     ""
}

@images {
    path_regexp \.(png|gif|jpe?g)$
}
handle @images {
    header Vary Accept
    try_files {path}.{avif_suffix} {path}.{webp_suffix} {path}.{jxl_suffix} {path} =404
}
```

`try_files` lists the formats in order of preference (AVIF → WebP → JPEG XL). Caddy serves the first sibling it finds on disk and falls back to the original. A format the client didn't ask for becomes an empty suffix (`{path}.`), which never matches a real file and is skipped.

If you only generate `.webp`, keep only the `webp_suffix` map and use `try_files {path}.{webp_suffix} {path}`.

> [!NOTE]
> Caddy sets the `Content-Type` from the extension of the **served sibling** (through Go's MIME database and your system's `mime.types`). `.webp` and `.avif` usually work out of the box. If `.jxl` comes back as `application/octet-stream`, add `image/jxl jxl` to your `mime.types`. Check it with `curl -I` as in [Check that it works](../README.md#check-that-it-works).

### Restrict by user agent (optional)

Add a `header User-Agent` condition to the `@images` matcher. Requests from other agents skip the `handle` and get the original file:

```caddy
@images {
    path_regexp \.(png|gif|jpe?g)$
    header User-Agent *Chrome* *Firefox* *Edge*
}
```
