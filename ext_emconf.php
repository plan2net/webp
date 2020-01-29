<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Create a WebP copy for images (TYPO3 CMS)',
    'description' => 'Adds automatically created _WebP_ copies of all JPEG and PNG images processed by TYPO3',
    'category' => 'fe',
    'author' => 'Wolfgang Klinger',
    'author_email' => 'wk@plan2.net',
    'state' => 'stable',
    'author_company' => 'plan2net GmbH',
    'version' => '2.2.1',
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
