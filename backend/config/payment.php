<?php

return [
    'default_gateway' => env('PAYMENT_DEFAULT_GATEWAY', 'stripe'),
    'default_currency' => env('PAYMENT_DEFAULT_CURRENCY', 'CNY'),
    'base_currency' => env('PAYMENT_BASE_CURRENCY', 'CNY'),

    'supported_currencies' => ['CNY', 'USD', 'EUR', 'GBP', 'JPY'],

    'exchange_rates' => [
        'CNY' => 1.000000,
        'USD' => 7.250000,
        'EUR' => 7.850000,
        'GBP' => 9.150000,
        'JPY' => 0.048500,
    ],

    'gateways' => [
        'stripe' => [
            'secret_key' => env('STRIPE_SECRET_KEY', ''),
            'publishable_key' => env('STRIPE_PUBLISHABLE_KEY', ''),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET', ''),
        ],
        'alipay' => [
            'app_id' => env('ALIPAY_APP_ID', ''),
            'private_key' => env('ALIPAY_PRIVATE_KEY', ''),
            'public_key' => env('ALIPAY_PUBLIC_KEY', ''),
            'gateway_url' => env('ALIPAY_GATEWAY_URL', 'https://openapi.alipay.com/gateway.do'),
            'notify_url' => env('ALIPAY_NOTIFY_URL', ''),
        ],
        'wechat' => [
            'app_id' => env('WECHAT_PAY_APP_ID', ''),
            'mch_id' => env('WECHAT_PAY_MCH_ID', ''),
            'api_key' => env('WECHAT_PAY_API_KEY', ''),
            'notify_url' => env('WECHAT_PAY_NOTIFY_URL', ''),
        ],
    ],

    'tax' => [
        'default_region' => env('TAX_DEFAULT_REGION', 'CN'),
        'enabled' => env('TAX_ENABLED', true),
    ],
];
