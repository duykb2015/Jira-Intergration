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

    'jira' => [
        'base_url' => env('JIRA_BASE_URL'),
        'email' => env('JIRA_EMAIL'),
        'api_token' => env('JIRA_API_TOKEN'),
        'project_key' => env('JIRA_PROJECT_KEY'),
        'start_date_field' => env('JIRA_START_DATE_FIELD', 'customfield_10010'),
    ],

    'clockify' => [
        'base_url' => env('CLOCKIFY_BASE_URL', 'https://api.clockify.me'),
        'timeout' => (int) env('CLOCKIFY_TIMEOUT', 15),
        'retries' => (int) env('CLOCKIFY_RETRIES', 2),
    ],

    'teamboard' => [
        'base_url' => env('TEAMBOARD_BASE_URL'),
        'api_token' => env('TEAMBOARD_API_TOKEN'),
        'timeout' => (int) env('TEAMBOARD_TIMEOUT', 15),
        'retries' => (int) env('TEAMBOARD_RETRIES', 2),
    ],

    'internal_admin' => [
        'username' => env('INTERNAL_ADMIN_USERNAME'),
        'password' => env('INTERNAL_ADMIN_PASSWORD'),
    ],

];
