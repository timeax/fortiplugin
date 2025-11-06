<?php

// config('fortiplugin.ui.scheme')
return [
    'sections' => [
        'header.main' => ['extendable' => true, /* ... */],
        'sidebar.primary' => ['extendable' => true, /* ... */],
    ],
    'floating' => [
        'zones' => [
            'bottom-right' => [
                'extendable' => true,
                'extraProps' => [
                    'ariaLabel' => ['type' => 'string'],
                    'priority' => ['type' => 'number']
                ],
                'allowUnknownProps' => false
            ],
            'bottom-left' => ['extendable' => true]
        ]
    ]
];