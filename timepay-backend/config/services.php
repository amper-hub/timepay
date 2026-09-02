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

    'faceplusplus' => [
        'key' => env('FACEPP_API_KEY'),
        'secret' => env('FACEPP_API_SECRET'),
        'compare_url' => env('FACEPP_COMPARE_URL', 'https://api-us.faceplusplus.com/facepp/v3/compare'),
        'detect_url' => env('FACEPP_DETECT_URL', 'https://api-us.faceplusplus.com/facepp/v3/detect'),
        'confidence_threshold' => (float) env('FACEPP_CONFIDENCE_THRESHOLD', 80),
        'audit_return_attributes' => env('FACEPP_AUDIT_RETURN_ATTRIBUTES', 'facequality,blur'),
        'audit_min_facequality' => (float) env('FACEPP_AUDIT_MIN_FACEQUALITY', 50),
        'audit_min_lighting' => (float) env('FACEPP_AUDIT_MIN_LIGHTING', 35),
        'audit_min_liveness' => (float) env('FACEPP_AUDIT_MIN_LIVENESS', 0.65),
    ],

];
