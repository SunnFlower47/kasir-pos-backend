<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => [
        'https://kasir-pos.sunnflower.site',
        'https://luma-pos.sunnflower.site',
        'https://lumapos-web.sunnflower.site/'
        // 'http://localhost:5174', // Frontend Development
        // 'http://localhost:4173', // Alternative Frontend Port
        // 'http://localhost:4174', // Current Frontend Port
        // 'http://localhost:3000', // React Standard Port
        // Note: Electron app origins are handled by AllowElectronOrigin middleware
        // This allows Electron app to work without exposing localhost to web browser
    ],

    'allowed_origins_patterns' => [
        '/^exp:\/\/.*/', // Expo Go URLs (exp://host:port)
        '/^http:\/\/192\.168\.\d+\.\d+:8081$/', // LAN IP addresses for Expo
        '/^http:\/\/10\.\d+\.\d+\.\d+:8081$/', // Private network IPs for Expo
    ],

    'allowed_headers' => [
        'Content-Type',
        'Authorization',
        'X-Requested-With',
        'X-Client-Type',
        'X-Client-Version',
        'Accept',
        'Origin',
    ],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true, // Allow credentials for authenticated requests

];
