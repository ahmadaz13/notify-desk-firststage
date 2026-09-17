<?php

return [
    'accounts' => [
        'ahmad' => [
            'name' => 'Ahmad',
            'email' => env('NOTIFY_FOUNDER_AHMAD_EMAIL', 'ahmad@notifydisk.com'),
            'password' => env('NOTIFY_FOUNDER_AHMAD_PASSWORD'),
        ],
        'khalid' => [
            'name' => 'Khalid',
            'email' => env('NOTIFY_FOUNDER_KHALID_EMAIL', 'khalid@notifydisk.com'),
            'password' => env('NOTIFY_FOUNDER_KHALID_PASSWORD'),
        ],
    ],
];
