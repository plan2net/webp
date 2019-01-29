.. include:: ../Includes.txt


.. _introduction:

Introduction
============


.. _what-it-does:

What does it do?
----------------

Adds an automatically created _WebP_ copy for every processed JPEG or PNG image in the following format.

  original.jpg.webp

What is WebP and why do I want it?
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

  WebP is a modern image format that provides superior lossless and lossy compression for images on the web. Using WebP, webmasters and web
  developers can create smaller, richer images that make the web faster.
  WebP lossless images are 26% smaller in size compared to PNGs. WebP lossy images are 25-34% smaller than comparable JPEG images at equivalent
  SSIM quality index.
  Lossless WebP supports transparency (also known as alpha channel) at a cost of just 22% additional bytes. For cases when lossy RGB
  compression is acceptable, lossy WebP also supports transparency, typically providing 3× smaller file sizes compared to PNG.

  -- Source: `A new image format for the Web <https://developers.google.com/speed/webp/>`_

Drawbacks
---------

Note that this extension produces an additional load on your server (each processed image is reprocessed) and possibly creates a lot of
additional files that consume disk space. Size varies depending on your ImageMagick/GraphicsMagick configuration.

Alternatives
------------

You can get an equal result with using the `PageSpeed Module <https://developers.google.com/speed/pagespeed/module/>`_ for
Apache *mod_pagespeed* or nginx *ngx_pagespeed* with a configuration like this:

.. code-block:: nginx

  pagespeed EnableFilters convert_jpeg_to_webp;
  pagespeed EnableFilters convert_to_webp_lossless;

But that requires more knowledge to set up.

Inspiration
-----------

This extension was inspired by Angela Dudtkowski's `cs_webp <https://extensions.typo3.org/extension/cs_webp/>`_ extension that has some flaws
and got no update since early 2017.

Thanks Angela :-) 
