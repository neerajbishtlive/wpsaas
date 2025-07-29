<?php

return [
    /*
    |--------------------------------------------------------------------------
    | SaaS Platform Configuration
    |--------------------------------------------------------------------------
    |
    | Configure your WordPress SaaS platform settings here.
    |
    */

    // Main domain for the platform
    'domain' => env('APP_DOMAIN', 'wpsaas.in'),
    
    // Protocol (http or https)
    'protocol' => env('APP_PROTOCOL', 'https'),
    
    // Use wildcard subdomains
    'wildcard' => env('APP_WILDCARD_DOMAIN', true),
    
    // Local development domain
    'local_domain' => env('APP_LOCAL_DOMAIN', 'wptest.local'),
    
    // Get the current domain based on environment
    'current_domain' => function() {
        if (app()->environment('local')) {
            return env('APP_LOCAL_DOMAIN', 'wptest.local');
        }
        return env('APP_DOMAIN', 'wpsaas.in');
    },
    
    // Get the current protocol based on environment
    'current_protocol' => function() {
        if (app()->environment('local')) {
            return 'http';
        }
        return env('APP_PROTOCOL', 'https');
    },
];