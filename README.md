# plan2net/webp for TYPO3 CMS

Adds a webp copy for every processed jpg/png image in the format

```
original.jpg.webp
```

# Installation

Add via composer.json: 

```
"require": {
    "plan2net/webp": "0.9.0"
}
```

install and activate in the Extension manager and clear your processed files in the Install Tool.

# Extension manager configuration

```
# cat=basic; type=string; label=Webp ImageMagick or GraphicsMagick conversion parameters
magick_parameters = -quality=85 -define webp:lossless=false
```

You find a list of possible options here:
https://www.imagemagick.org/script/webp.php
or here:
http://www.graphicsmagick.org/GraphicsMagick.html

# Webserver configuration

## nginx

Add a map directive in your global nginx configuration:

```
map $http_accept $webp_suffix {
    default   "";
    "~*webp"  ".webp";
}
```

and add these rules to your `server` configuration:

```
location ~* ^/fileadmin/_processed_/.+\.(png|jpg)$ {
        add_header Vary Accept;
        try_files $uri$webp_suffix $uri =404;
}
```

## Apache .htaccess

```
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
```
