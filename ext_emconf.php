<?php

$EM_CONF['webp'] = [
    'title' => 'Creates WebP copies for images',
    'description' => 'Creates WebP copies of all jpeg and png images',
    'category' => 'fe',
    'author' => 'Wolfgang Klinger',
    'author_email' => 'wk@plan2.net',
    'state' => 'stable',
    'author_company' => 'plan2net GmbH',
    'version' => '13.0.1',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.14-13.9.99',
        ],
        'conflicts' => [
        ],
        'suggests' => [
        ],
    ],
    'suggests' => [
    ],
];
