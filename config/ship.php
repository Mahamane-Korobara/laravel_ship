<?php

return [
    'allow_infra_setup' => env('SHIP_ALLOW_INFRA_SETUP', false),
    'auto_setup_infra' => env('SHIP_AUTO_SETUP_INFRA', false),
    'allow_docker_setup' => env('SHIP_ALLOW_DOCKER_SETUP', true),
    'auto_install_docker' => env('SHIP_AUTO_INSTALL_DOCKER', true),
    'auto_install_project_deps' => env('SHIP_AUTO_INSTALL_PROJECT_DEPS', true),
    'agent_port' => (int) env('SHIP_AGENT_PORT', 8081),
    'agent_allow_all' => env('SHIP_AGENT_ALLOW_ALL', true),
    'agent_allow_prefixes' => env('SHIP_AGENT_ALLOW_PREFIXES', 'docker,git,ln,mkdir,rm,cp,mv,chmod,chown,find,ls,echo,cat,sed,awk,tail,head,cut,tr,xargs,touch,test,sh'),
    'e2e_port' => (int) env('SHIP_E2E_PORT', 18080),
    'e2e_repo' => env('SHIP_E2E_REPO'),
    'e2e_safe' => env('SHIP_E2E_SAFE', true),
    'e2e_install_dev' => env('SHIP_E2E_INSTALL_DEV', true),
    'docker_exclude_storage' => env('SHIP_DOCKER_EXCLUDE_STORAGE', true),
    'reverb_host' => env('REVERB_SERVER_HOST', '0.0.0.0'),
    'reverb_port' => (int) env('REVERB_SERVER_PORT', 8080),
    'cron_user' => env('SHIP_CRON_USER', 'www-data'),
    'cron_php' => env('SHIP_CRON_PHP', '/usr/bin/php'),
];
