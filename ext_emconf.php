<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Creates WebP copies for images',
    'description' => 'Creates WebP copies of all jpeg and png images',
    'category' => 'fe',
    'author' => 'Wolfgang Klinger',
    'author_email' => 'wk@plan2.net',
    'state' => 'stable',
    'author_company' => 'plan2net GmbH',
    'version' => '4.1.0',
    'constraints' => [
        'depends' => [
            'typo3' => '10.4.0-11.5.99'
        ],
        'conflicts' => [
        ],
        'suggests' => [
        ],
    ],
    'suggests' => [
    ],
];
