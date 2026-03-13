<?php

return [

    'default' => env('REVERB_SERVER', 'reverb'),

    'servers' => [
        'reverb' => [
            'host' => env('REVERB_SERVER_HOST', '0.0.0.0'),
            'port' => env('REVERB_SERVER_PORT', 8080),
            'scheme' => env('REVERB_SERVER_SCHEME', 'http'),
            'app_id' => env('REVERB_APP_ID'),
            'app_key' => env('REVERB_APP_KEY'),
            'app_secret' => env('REVERB_APP_SECRET'),
            'allowed_origins' => array_filter(explode(',', env('REVERB_ALLOWED_ORIGINS', '*'))),
            'max_request_size' => (int) env('REVERB_MAX_REQUEST_SIZE', 10000),
            'scaling' => [
                'enabled' => false,
                'channel' => env('REVERB_SCALING_CHANNEL', 'reverb'),
                'server' => [],
            ],
        ],
    ],

];
