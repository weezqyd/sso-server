<?php

return [
    'brokers' => [
        'cytonnTaskManager' => [
            'serverUrl' => env('SSO_SERVER_URL', 'http://localhost:9000/login/sso'),
            'appId' => env('SSO_APP1_ID'),
            'secret' => env('SSO_APP1_SECRET'),
        ],
    ],
];
