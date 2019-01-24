# WebP for TYPO3 CMS LTS 8 and 9

Adds an automagically created _WebP_ copy for every processed jpg/png image in the format

    original.jpg.webp
    
# What is WebP and why do I want it?

> WebP is a modern image format that provides superior lossless and lossy compression for images on the web. Using WebP, webmasters and web developers can create smaller, richer images that make the web faster.
>  
>  WebP lossless images are 26% smaller in size compared to PNGs. WebP lossy images are 25-34% smaller than comparable JPEG images at equivalent SSIM quality index.
>  
>  Lossless WebP supports transparency (also known as alpha channel) at a cost of just 22% additional bytes. For cases when lossy RGB compression is acceptable, lossy WebP also supports transparency, typically providing 3× smaller file sizes compared to PNG.

   — source: https://developers.google.com/speed/webp/

# Installation

Add via composer.json: 

    "require": {
        "plan2net/webp": "^1.0"
    }

Install and activate the extension in the Extension manager and clear your processed files in the Install Tool or Maintenance module.

# Extension manager configuration

You can set parameters for the conversion in the extension configuration. 

    # cat=basic; type=string; label=Webp ImageMagick or GraphicsMagick conversion parameters
    magick_parameters =

You find a list of possible options here:
https://www.imagemagick.org/script/webp.php
or here:
http://www.graphicsmagick.org/GraphicsMagick.html

Default value is:

    -quality 95 -define webp:lossless=false

which has (in our experience) a minor to no impact on visual difference to the original image.

*Warning*

Try to set a higher value for `quality` first if the image does not fit your expectations,
before trying to use `webp:lossless=true`, as this could even lead to a
higher filesize than the original!

# Webserver configuration

## nginx

Add a map directive in your global nginx configuration:

    map $http_accept $webp_suffix {
        default   "";
        "~*webp"  ".webp";
    }

and add these rules to your `server` configuration:

    location ~* ^/fileadmin/_processed_/.+\.(png|jpg)$ {
            add_header Vary Accept;
            try_files $uri$webp_suffix $uri =404;
    }

## Apache (.htaccess example)

    <IfModule mod_rewrite.c>
        RewriteEngine On
        RewriteCond %{HTTP_ACCEPT} image/webp
        RewriteCond %{DOCUMENT_ROOT}/$1.$2.webp -f
        RewriteRule ^(fileadmin/_processed_.+)\.(jpg|png)$ $1.$2.webp [T=image/webp,E=accept:1]
    </IfModule>

    <IfModule mod_headers.c>
        Header append Vary Accept env=REDIRECT_accept
    </IfModule>

    AddType image/webp .webp
    
# Alternatives

You can get an equal result with using the Apache _mod_pagespeed_ or nginx _ngx_pagespeed_ modules from Google https://developers.google.com/speed/pagespeed/module/ with a configuration like:

    pagespeed EnableFilters convert_jpeg_to_webp;
    pagespeed EnableFilters convert_to_webp_lossless;
    
but that requires more knowledge to set up.

# Inspiration

This extension was inspired by Angela Dudtkowski's _cs_webp_ extension that has some flaws and got no update since early 2017. Thanks Angela :-) 

