<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default instance
    |--------------------------------------------------------------------------
    |
    | The instance used by WhatsApp::sendMessage() and the notification channel
    | when you don't pick one with WhatsApp::instance('name').
    |
    */

    'default' => env('DARORY_WA_DEFAULT', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Instances
    |--------------------------------------------------------------------------
    |
    | One entry per connected WhatsApp number. Copy the API token and the
    | instance ID from the Darory client area (WhatsApp > your number > API).
    |
    */

    'instances' => [
        'default' => [
            'token' => env('DARORY_WA_TOKEN'),
            'instance_id' => env('DARORY_WA_INSTANCE_ID'),
        ],

        // 'sales' => [
        //     'token' => env('DARORY_WA_SALES_TOKEN'),
        //     'instance_id' => env('DARORY_WA_SALES_INSTANCE_ID'),
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Gateway
    |--------------------------------------------------------------------------
    */

    'base_url' => env('DARORY_WA_URL', 'https://wa.darory.com/wa-api'),

    // Seconds to wait for a response. Sending a large file can take a while.
    'timeout' => (float) env('DARORY_WA_TIMEOUT', 60),

    /*
    |--------------------------------------------------------------------------
    | Incoming webhook
    |--------------------------------------------------------------------------
    |
    | The package adds a POST route that receives incoming messages and fires the
    | Darory\WhatsApp\Laravel\Events\MessageReceived event. Run
    | "php artisan darory-whatsapp:check" to print the URL, then add it as a
    | "message" webhook on your instance in the Darory client area.
    |
    | The gateway does not sign webhooks, so set a long random secret: it is
    | added to the URL and requests without it are rejected.
    |
    */

    'webhook' => [
        'enabled' => env('DARORY_WA_WEBHOOK_ENABLED', true),
        'path' => env('DARORY_WA_WEBHOOK_PATH', 'darory-whatsapp/webhook'),
        'secret' => env('DARORY_WA_WEBHOOK_SECRET'),
        'middleware' => [],

        // Messages you send from the phone itself are also delivered; skip them by default
        'ignore_own_messages' => true,
    ],

];
