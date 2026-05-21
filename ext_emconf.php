<?php

$EM_CONF['webp'] = [
    'title' => 'Drop-in WebP delivery',
    'description' => 'Creates sibling WebP files next to each processed image. The webserver delivers them via Accept-header content negotiation, so image URLs and HTML stay unchanged.',
    'category' => 'fe',
    'author' => 'Wolfgang Klinger',
    'author_email' => 'wk@plan2.net',
    'state' => 'stable',
    'author_company' => 'plan2net GmbH',
    'version' => '14.4.1',
    'constraints' => [
        'depends' => [
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
