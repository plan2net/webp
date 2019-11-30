<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Conversion of processed TYPO3 images to WebP format',
    'description' => 'Adds automatically created _WebP_ copies of all JPEG and PNG images processed by TYPO3.',
    'category' => 'fe',
    'author' => 'Wolfgang Klinger',
    'author_email' => 'wk@plan2.net',
    'state' => 'stable',
    'author_company' => 'plan2net GmbH',
    'version' => '1.2.0',
    'constraints' => [
        'depends' => [
            'typo3' => '8.7.0-9.5.99'
        ],
        'conflicts' => [
        ],
        'suggests' => [
        ],
    ],
    'suggests' => [
    ],
];
