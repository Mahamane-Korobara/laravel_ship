<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Chemin de base des projets déployés
    |--------------------------------------------------------------------------
    */
    'base_path' => env('DEPLOY_BASE_PATH', '/var/www/projects'),

    /*
    |--------------------------------------------------------------------------
    | Nombre maximum de releases à conserver par projet
    |--------------------------------------------------------------------------
    */
    'max_releases' => env('DEPLOY_MAX_RELEASES', 5),

    /*
    |--------------------------------------------------------------------------
    | Email admin pour les certificats SSL Let's Encrypt
    |--------------------------------------------------------------------------
    */
    'admin_email' => env('ADMIN_EMAIL', 'admin@example.com'),
];
