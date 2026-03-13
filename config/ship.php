<?php

return [
    'allow_infra_setup' => env('SHIP_ALLOW_INFRA_SETUP', false),
    'auto_setup_infra' => env('SHIP_AUTO_SETUP_INFRA', false),
    'auto_install_project_deps' => env('SHIP_AUTO_INSTALL_PROJECT_DEPS', true),
    'reverb_host' => env('REVERB_SERVER_HOST', '0.0.0.0'),
    'reverb_port' => (int) env('REVERB_SERVER_PORT', 8080),
    'cron_user' => env('SHIP_CRON_USER', 'www-data'),
    'cron_php' => env('SHIP_CRON_PHP', '/usr/bin/php'),
];
