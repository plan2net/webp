<?php

$EM_CONF['webp'] = [
    'title' => 'Creates WebP copies for images',
    'description' => 'Creates WebP copies of all jpeg and png images',
    'category' => 'fe',
    'author' => 'Wolfgang Klinger',
    'author_email' => 'wk@plan2.net',
    'state' => 'stable',
    'author_company' => 'plan2net GmbH',
    'version' => '4.3.2',
    'constraints' => [
        'depends' => [
            'typo3' => '10.4.43-11.5.99'
        ],
        'conflicts' => [
        ],
        'suggests' => [
        ],
    ],
    'suggests' => [
    ],
];