<?php

declare(strict_types=1);

// config for Hwkdo/IntranetAppAbwesenheit
return [
    'user_model' => env('ABWESENHEIT_USER_MODEL', \App\Models\User::class),
    'gvp_model' => env('ABWESENHEIT_GVP_MODEL', \App\Models\Gvp::class),
    'd3_api' => env('ABWESENHEIT_D3_API', \App\Services\Interfaces\D3ApiInterface::class),

    'roles' => [
        'admin' => [
            'name' => 'App-Abwesenheit-Admin',
            'permissions' => [
                'see-app-abwesenheit',
                'manage-app-abwesenheit',
            ],
        ],
        'user' => [
            'name' => 'App-Abwesenheit-Benutzer',
            'permissions' => [
                'see-app-abwesenheit',
            ],
            'all_users' => true,
        ],
    ],
];
