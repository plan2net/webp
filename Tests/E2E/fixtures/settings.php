<?php

return [
    'EXTENSIONS' => [
        'webp' => [
            'async' => '0',
            'async_throttle_ms' => '0',
            'convert_all' => '1',
            'converter' => 'Plan2net\\Webp\\Converter\\VipsConverter',
            'converter_avif' => 'Plan2net\\Webp\\Converter\\VipsConverter',
            'converter_jxl' => 'Plan2net\\Webp\\Converter\\VipsConverter',
            'exclude_directories' => '',
            'filter_pattern' => '/\\.(jpe?g|png|gif)\\.(webp|avif|jxl)$/i',
            'formats_enabled' => 'webp,avif,jxl',
            'hide_webp' => '1',
            'mime_types' => 'image/jpeg,image/png,image/gif',
            'mime_types_avif' => 'image/jpeg,image/png,image/gif',
            'mime_types_jxl' => 'image/jpeg,image/png,image/gif',
            'parameters' => 'image/jpeg::Q=85 smart_subsample=true effort=4|image/png::Q=75 lossless=true effort=4|image/gif::Q=75 lossless=true mixed=true effort=4',
            'parameters_avif' => 'image/jpeg::Q=60 effort=4|image/png::Q=60 effort=4|image/gif::Q=60 effort=4',
            'parameters_jxl' => 'image/jpeg::Q=75 effort=7|image/png::lossless=true effort=7|image/gif::lossless=true effort=7',
            'silent' => '0',
            'use_system_settings' => '0',
        ],
    ],
];
