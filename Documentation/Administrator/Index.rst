.. include:: ../Includes.txt


.. _admin-manual:

Administrator Manual
====================

Target group: **Administrators**

.. _admin-installation:

Installation
------------

Add via composer.json: 

  |"require": {
  |  "plan2net/webp": "^1.0"
  |}

Install and activate the extension in the Extension manager and clear your processed files in the Install Tool or Maintenance module.

.. _admin-configuration:

Configuration
-------------

Extension manager configuration
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

You can set parameters for the conversion in the extension configuration. 

  |# cat=basic; type=string; label=Webp ImageMagick or GraphicsMagick conversion parameters
  |magick_parameters =

You find a list of possible options here:

ImageMagick: https://www.imagemagick.org/script/webp.php
GraphicsMagick: http://www.graphicsmagick.org/GraphicsMagick.html and http://www.graphicsmagick.org/convert.html

Default value is:

  |-quality 85 -define webp:lossless=false

This has the least impact on visual difference to the original image.
Set *webp:lossless=true* for even smaller image sizes.

Web server configuration
^^^^^^^^^^^^^^^^^^^^^^^^

nginx
"""""

Add a map directive in your global nginx configuration:

  |map $http_accept $webp_suffix {
  |   default   "";
  |   "~*webp"  ".webp";
  |}

And add these rules to your *server* configuration:

  |location ~* ^/fileadmin/_processed_/.+\.(png|jpg)$ {
  |  add_header Vary Accept;
  |  try_files $uri$webp_suffix $uri =404;
  |}

Apache (.htaccess example)
""""""""""""""""""""""""""

Add the following lines to the *.htaccess* file of the document root:

  |<IfModule mod_rewrite.c>
  |  RewriteEngine On
  |  RewriteCond %{HTTP_ACCEPT} image/webp
  |  RewriteCond %{DOCUMENT_ROOT}/$1.$2.webp -f
  |  RewriteRule ^(fileadmin/_processed_.+)\.(jpg|png)$ $1.$2.webp [T=image/webp,E=accept:1]
  |</IfModule>
  |
  |<IfModule mod_headers.c>
  |  Header append Vary Accept env=REDIRECT_accept
  |</IfModule>
  |
  |AddType image/webp .webp
