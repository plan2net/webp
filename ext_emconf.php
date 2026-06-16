<?php

$EM_CONF['webp'] = [
    'title' => 'Drop-in WebP / AVIF / JPEG XL delivery',
    'description' => 'Creates sibling WebP, AVIF, and JPEG XL files next to each processed image. The webserver delivers the best match per request via Accept-header content negotiation, so image URLs and HTML stay unchanged.',
    'category' => 'fe',
    'author' => 'Wolfgang Klinger',
    'author_email' => 'wk@plan2.net',
    'state' => 'stable',
    'author_company' => 'plan2net GmbH',
    'version' => '14.7.0',
    'constraints' => [
        'depends' => [
            'php' => '8.2.0-8.4.99',
            'typo3' => '12.4.0-14.99.99',
        ],
        'conflicts' => [
        ],
        'suggests' => [
        ],
    ],
    'suggests' => [
    ],
];
