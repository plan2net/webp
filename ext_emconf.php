<?php

$EM_CONF['webp'] = [
    'title' => 'Creates WebP copies for images',
    'description' => 'Creates WebP copies of all jpeg and png images',
    'category' => 'fe',
    'author' => 'Wolfgang Klinger',
    'author_email' => 'wk@plan2.net',
    'state' => 'stable',
    'author_company' => 'plan2net GmbH',
    'version' => '5.0.1',
    'constraints' => [
        'depends' => [
            'typo3' => '12.4.0-12.9.99',
        ],
        'conflicts' => [
        ],
        'suggests' => [
        ],
    ],
    'suggests' => [
    ],
];
