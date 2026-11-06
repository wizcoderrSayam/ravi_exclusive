<?php

/*
 * Only what this store changes; the CMS's own config supplies the rest. Staff sign in
 * through the ecommerce package at /admin/login, so the panel sends guests there and
 * logs out through it. Who may open the panel is the `accessCms` gate in AppServiceProvider.
 */
return [
    'routes' => [
        'panel' => [
            'middleware' => ['web', 'auth'],
        ],
    ],

    'auth' => [
        'logout_route' => 'admin.logout',
    ],
];
