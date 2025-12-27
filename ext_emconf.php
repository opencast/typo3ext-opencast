<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Opencast',
    'description' => 'Support for Opencast videos',
    'category' => 'plugin',
    'version' => '1.1.3',
    'state' => 'stable',
    'author' => 'Philipp Kitzberger',
    'author_email' => 'beratung@rz.uni-osnabrueck.de',
    'author_company' => 'Osnabrück University',
    'constraints' => [
        'depends' => [
            'typo3' => '12.4.0-13.4.99',
        ],
        'conflicts' => [
        ],
        'suggests' => [
        ],
    ],
];
