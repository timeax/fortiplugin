<?php


return [
    'models' => [
        // Example: User model, mapped as 'user'
        'user' => [
            'table' => 'users',
            'relations' => [
                // alias     => related model alias
                'posts'    => 'post',     // 'posts' relation on User maps to 'post'
                'profile'  => 'profile',  // 'profile' relation on User maps to 'profile'
                'comments' => 'comment',  // 'comments' relation on User maps to 'comment'
            ],
        ],
        // Example: Post model, mapped as 'post'
        'post' => [
            'table' => 'posts',
            'relations' => [
                'user'     => 'user',    // inverse relation
                'comments' => 'comment',
                'tags'     => 'tag',
            ],
        ],
        // More models...
        'profile' => [
            'table' => 'profiles',
            'relations' => [
                'user' => 'user',
            ],
        ],
        'comment' => [
            'table' => 'comments',
            'relations' => [
                'user' => 'user',
                'post' => 'post',
            ],
        ],
        // etc.
    ],
]