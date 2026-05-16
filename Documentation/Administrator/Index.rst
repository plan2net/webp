.. _admin-manual:

Administration Manual
=====================

Target group: **Administrators**

.. _admin-installation:

Requirements
------------

Your version of ImageMagick or GraphicsMagick on the server needs to support WebP.

How to test
^^^^^^^^^^^

You can test the support on the command line:

GraphicsMagick
""""""""""""""

.. code-block:: bash

  gm version | grep WebP

This should return "*yes*".

ImageMagick
"""""""""""

.. code-block:: bash

  convert version | grep webp

This should return a list of supported formats including WebP.

Installation
------------

Add via composer.json:

.. code-block:: bash

  composer require "plan2net/webp"


Install and activate the extension in the Extension manager and clear your processed files in the Install Tool or Maintenance module.

.. _admin-configuration:

Configuration
-------------

Extension manager configuration
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

You can set parameters for the conversion in the extension configuration.

.. code-block:: none

  # cat=basic; type=string; label=Webp conversion parameters (for internal or external adapter)
  parameters =

You find a list of possible options here:

:ImageMagick:    https://www.imagemagick.org/script/webp.php
:GraphicsMagick: http://www.graphicsmagick.org/GraphicsMagick.html and http://www.graphicsmagick.org/convert.html

Default value is:

.. code-block:: none

  -quality 95 -define webp:lossless=false

This has (in our experience) a minor to no impact on visual difference to the original image.

.. warning::

  Try to set a higher value for quality first if the image does not fit your expectations, before trying to use *webp:lossless=true*.
  This could even lead to a larger filesize than the original!

Web server configuration
^^^^^^^^^^^^^^^^^^^^^^^^

nginx
"""""

Add a map directive in your *global* configuration:

.. code-block:: nginx

  map $http_accept $webp_suffix {
     default   "";
     "~*webp"  ".webp";
  }

And add these rules to your *server* configuration:

.. code-block:: nginx

  location ~* ^/fileadmin/.+\.(png|jpg|jpeg)$ {
          add_header Vary Accept;
          try_files $uri$webp_suffix $uri =404;
  }
  location ~* ^/other-storage/.+\.(png|jpg|jpeg)$ {
          add_header Vary Accept;
          try_files $uri$webp_suffix $uri =404;
  }

Apache (.htaccess example)
""""""""""""""""""""""""""

Add the following lines to the *.htaccess* file of the document root:

.. code-block:: apache

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


Remote storages (S3, Azure, custom FAL drivers)
-----------------------------------------------

Each storage record (*File > Storage*) carries a *Generate WebP variants*
field that selects the per-storage mode:

- **Auto** (default) — on for ``driver = Local``, off for everything else.
  Matches pre-14.2 behaviour for every existing storage.
- **Enabled** — on regardless of driver type. Use this to opt a non-Local
  storage in.
- **Disabled** — off regardless of driver type. Use this to temporarily
  take a Local storage out of the pipeline.

When enabled, behaviour on a non-Local storage is identical to Local: the
``.webp`` lands at ``<original>.webp`` on the storage, and the FAL lifecycle
events (move, replace, delete, recycler) keep siblings in sync.

.. important::

   Enable ``async = 1`` for any storage with a non-Local driver. Synchronous
   mode adds the driver's upload latency to every page render that processes
   an image (typical S3 PUT: 100–500 ms). The async queue moves that work off
   the render path.

The webserver rewrites above apply unchanged when TYPO3's origin sits in
front of the storage. When the storage is served via a CDN directly (S3 +
CloudFront, etc.), the ``Accept``-header rewrite has to be done at the edge —
e.g. a CloudFront Function on viewer request, a Cloudflare Worker, or an
origin proxy. The extension only writes the sibling on the storage; choosing
which file to serve per request is the edge's job.


Diagnosing your installation
----------------------------

The ``webp:diagnose`` CLI command walks the full WebP delivery chain
end-to-end and points at the first failing link.

.. code-block:: bash

   vendor/bin/typo3 webp:diagnose                              # health check
   vendor/bin/typo3 webp:diagnose --url=https://example.com    # also probe webserver delivery
   vendor/bin/typo3 webp:diagnose --file=42                    # also investigate one file

It reports:

- Storages: mode, driver, sibling count, plus phantom rows with
  unregistered drivers.
- Converter: class, binary availability, parameter parsing.
- Async pipeline: queue size, age, scheduler task state.
- Failed-conversion cache: total, recent rows, dominant config hash.
- Delivery probe (``--url=…``): two ``Accept`` HEADs + ``Vary: Accept``.
- File deep dive (``--file=<uid>``): metadata + both sibling tables +
  failed-attempts rows.

.. note::

   The probe runs from this machine. CDN behaviour at the edge can differ
   from what we observe locally. Run the probe from a host inside your
   CDN's pull zone for the most accurate read.

Useful flags:

``--insecure``
   Disable TLS certificate verification on the HTTP probe -- for
   self-signed or otherwise untrusted certs.

``--probe-timeout=<sec>``
   HTTP probe timeout (default: 10).
