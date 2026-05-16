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

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'marketing_ai' => [
        'api_key' => env('MARKETING_AI_API_KEY'),
    ],

    'google' => [
        'gsc_client_id' => env('GSC_CLIENT_ID'),
        'gsc_client_secret' => env('GSC_CLIENT_SECRET'),
        'gsc_refresh_token' => env('GSC_REFRESH_TOKEN'),
        'gsc_site_url' => env('GSC_SITE_URL', 'https://kilatech.ma/'),
    ],


];
