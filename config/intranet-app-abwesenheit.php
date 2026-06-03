<?php

// config for Hwkdo/IntranetAppAbwesenheit
return [
'roles' => [
        'admin' => [
            'name' => 'App-Abwesenheit-Admin',
            'permissions' => [
                'see-app-abwesenheit',
                'manage-app-abwesenheit',
            ]
        ],
        'user' => [
            'name' => 'App-Abwesenheit-Benutzer',
            'permissions' => [
                'see-app-abwesenheit',                
            ]
        ],
]
];
