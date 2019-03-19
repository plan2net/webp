# WebP for TYPO3 CMS LTS 8 and 9

Adds an automagically created _WebP_ copy for every processed jpg/jpeg/png image in the format

    original.ext.webp
    
# What is WebP and why do I want it?

> WebP is a modern image format that provides superior lossless and lossy compression for images on the web. Using WebP, webmasters and web developers can create smaller, richer images that make the web faster.
>  
>  WebP lossless images are 26% smaller in size compared to PNGs. WebP lossy images are 25-34% smaller than comparable JPEG images at equivalent SSIM quality index.
>  
>  Lossless WebP supports transparency (also known as alpha channel) at a cost of just 22% additional bytes. For cases when lossy RGB compression is acceptable, lossy WebP also supports transparency, typically providing 3× smaller file sizes compared to PNG.

   — source: https://developers.google.com/speed/webp/

# Installation

Add via composer: 

    composer require "plan2net/webp"

* Install and activate the extension in the Extension manager 
* Flush TYPO3 and PHP Cache
* Clear the processed files in the Install Tool or Maintenance module

# Requirements

Your version of ImageMagick or GraphicsMagick on the server needs to support WebP (obviously).

You can test the support of GraphicsMagick with:

    gm version | grep WebP

(should return `yes`)

or using ImageMagick with: 

    convert version | grep webp

(should return a list of supported formats including `webp`)

# Extension manager configuration

You can set parameters for the conversion in the extension configuration. 

## `magick_parameters`

    # cat=basic; type=string; label=Webp ImageMagick or GraphicsMagick conversion parameters
    magick_parameters = -quality 95 -define webp:lossless=false

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

## `convert_all_images`

    # cat=basic; type=boolean; label=Convert all images in local and writable storage and save a copy as Webp; disable to convert images in the _processed_ folder only
    convert_all_images = 1
    
Since version `1.1.0` all images in every local and writable storage will be converted to Webp by default (instead of just images modified by TYPO3 in the storage's processed folder). If you want to revert to the previous behaviour, set this flag to `false` (disable the checkbox).

# Webserver example configuration

Please adapt the following to _your specific needs_, this is only an example configuration.

## nginx

Add a map directive in your global nginx configuration:

    map $http_accept $webp_suffix {
        default   "";
        "~*webp"  ".webp";
    }

and add these rules to your `server` configuration:

    location ~* ^/fileadmin/.+\.(png|jpg|jpeg)$ {
            add_header Vary Accept;
            try_files $uri$webp_suffix $uri =404;
    }
    location ~* ^/other-storage/.+\.(png|jpg|jpeg)$ {
            add_header Vary Accept;
            try_files $uri$webp_suffix $uri =404;
    }

Make sure that there are no other rules that already apply to the specified image formats and prevent further execution!

## Apache (.htaccess example)

    <IfModule mod_rewrite.c>
        RewriteEngine On
        RewriteCond %{HTTP_ACCEPT} image/webp
        RewriteCond %{DOCUMENT_ROOT}/$1.$2.webp -f
        RewriteRule ^(fileadmin/.+)\.(png|jpg|jpeg)$ $1.$2.webp [T=image/webp,E=accept:1]
        RewriteRule ^(other-storage/.+)\.(png|jpg|jpeg)$ $1.$2.webp [T=image/webp,E=accept:1]
    </IfModule>

    <IfModule mod_headers.c>
        Header append Vary Accept env=REDIRECT_accept
    </IfModule>

    AddType image/webp .webp
    
Make sure that there are no other rules that already apply to the specified image formats and prevent further execution!

# Removing processed files

You can remove the created .webp files at any time within the TYPO3 CMS backend.

## TYPO3 CMS LTS 8.7

* Go to System > Install > Clean up
* Click the _Clear processed files_ button

## TYPO3 CMS LTS 9.5

* Go to Admin Tools > Remove Temporary Assets
* Click the _Scan temporary files_ button
* In the modal click the button with the path of the storage

Although the button names only the path of the \_processed\_ folder, all processed files of the storage are actually deleted!

# Alternatives

You can get an equal result with using the Apache _mod_pagespeed_ or nginx _ngx_pagespeed_ modules from Google https://developers.google.com/speed/pagespeed/module/ with a configuration like:

    pagespeed EnableFilters convert_jpeg_to_webp;
    pagespeed EnableFilters convert_to_webp_lossless;
    
but that requires more knowledge to set up.

# Drawbacks to keep in mind

Note that this extension produces an additional load on your server (each processed image is reprocessed) and possibly creates a lot of additional files that consume disk space (size varies depending on your ImageMagick/GraphicsMagick configuration).

# Inspiration

This extension was inspired by Angela Dudtkowski's _cs_webp_ extension that has some flaws and got no update since early 2017. Thanks Angela :-) 

# Changelog

| Release       | Changes
| ------------- |-------------
| 1.1.0         | Convert all images in every local and writable storage<br>Fix fallback options for conversion<br>Update README
