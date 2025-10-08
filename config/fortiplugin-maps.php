<?php


return [
    'notifications-channels' => [
        // push and so on
    ],
    "modules" => [
        "auth-service" => [
            "map" => "App\\Modules\\AuthService\\AuthService",
            "docs" => "https://docs.fortiplugin.com/modules/auth-service" // must be set by host
        ]
    ],
    'models' => [
        // Example: User model, mapped as 'user'
        'user' => [
            'map' => 'App\\Models\\User',
            'relations' => [
                // alias     => related model alias
                'posts' => 'post',     // 'posts' relation on User maps to 'post'
                'profile' => 'profile',  // 'profile' relation on User maps to 'profile'
                'comments' => 'comment',  // 'comments' relation on User maps to 'comment'
            ],
            'columns' => [
                "all" => [], // all accessible columns, columns that are not in this list are not accessible
                "writable" => [] // columns that are writable by the plugin. Note that this is a subset of 'all'
            ]
        ],
        // Example: Post model, mapped as 'post'
        'post' => [
            'map' => 'App\Models\Post',
            'relations' => [
                'user' => 'user',    // inverse relation
                'comments' => 'comment',
                'tags' => 'tag',
            ],
        ],
        // etc.
    ],
];