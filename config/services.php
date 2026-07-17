<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'hikvision' => [
        'base_url' => env('HIKVISION_BASE_URL'),
        'username' => env('HIKVISION_USERNAME'),
        'password' => env('HIKVISION_PASSWORD'),
        'timeout' => (int) env('HIKVISION_TIMEOUT', 3),
        'connect_timeout' => (int) env('HIKVISION_CONNECT_TIMEOUT', 3),
        'failure_cooldown' => (int) env('HIKVISION_FAILURE_COOLDOWN', 60),
        'user_endpoint' => env('HIKVISION_USER_ENDPOINT', '/ISAPI/AccessControl/UserInfo/Record?format=json'),
        'user_search_endpoint' => env('HIKVISION_USER_SEARCH_ENDPOINT', '/ISAPI/AccessControl/UserInfo/Search?format=json'),
    ],

];
