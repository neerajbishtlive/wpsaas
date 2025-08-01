<?php

return [
    /*
    |--------------------------------------------------------------------------
    | WordPress Paths
    |--------------------------------------------------------------------------
    */
    
    'core_path' => env('WP_CORE_PATH', base_path('../wordpress-core')),
    'sites_path' => env('SITES_PATH', base_path('../sites')),
    'scripts_path' => env('SCRIPTS_PATH', base_path('../scripts')),
    
    /*
    |--------------------------------------------------------------------------
    | WordPress Settings
    |--------------------------------------------------------------------------
    */
    
    'debug' => env('WP_DEBUG', false),
    'cache' => env('WP_CACHE', true),
    'disable_cron' => env('DISABLE_WP_CRON', true),
    
    /*
    |--------------------------------------------------------------------------
    | Resource Limits
    |--------------------------------------------------------------------------
    */
    
    'memory_limit' => env('PHP_MEMORY_LIMIT', '256M'),
    'max_execution_time' => env('PHP_MAX_EXECUTION_TIME', 300),
    'upload_max_filesize' => env('PHP_UPLOAD_MAX_FILESIZE', '50M'),
    'post_max_size' => env('PHP_POST_MAX_SIZE', '50M'),
];