<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Module Generator Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration options for the Laravel Filament
    | Module Generator package.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Default Model Path
    |--------------------------------------------------------------------------
    |
    | This is the default path where generated models will be created.
    | You can customize this to match your application's structure.
    |
    */
    'model_path' => 'app/Models',

    /*
    |--------------------------------------------------------------------------
    | Default Migration Path
    |--------------------------------------------------------------------------
    |
    | This is the default path where generated migrations will be created.
    |
    */
    'migration_path' => 'database/migrations',

    /*
    |--------------------------------------------------------------------------
    | Default Factory Path
    |--------------------------------------------------------------------------
    |
    | This is the default path where generated factories will be created.
    |
    */
    'factory_path' => 'database/factories',

    /*
    |--------------------------------------------------------------------------
    | Default Seeder Path
    |--------------------------------------------------------------------------
    |
    | This is the default path where generated seeders will be created.
    |
    */
    'seeder_path' => 'database/seeders',

    /*
    |--------------------------------------------------------------------------
    | Default Resource Path
    |--------------------------------------------------------------------------
    |
    | This is the default path where generated Filament resources will be created.
    |
    */
    'resource_path' => 'app/Filament/Resources',

    /*
    |--------------------------------------------------------------------------
    | Default Namespace Prefix
    |--------------------------------------------------------------------------
    |
    | This is the default namespace prefix for generated files.
    |
    */
    'namespace_prefix' => 'App',

    /*
    |--------------------------------------------------------------------------
    | Auto Run Migrations
    |--------------------------------------------------------------------------
    |
    | Whether to automatically run migrations after generating them.
    |
    */
    'auto_run_migrations' => false,

    /*
    |--------------------------------------------------------------------------
    | Auto Run Seeders
    |--------------------------------------------------------------------------
    |
    | Whether to automatically run seeders after generating them.
    |
    */
    'auto_run_seeders' => false,
];