<?php

return [
    'version' => '2026-10-06.v1',
    'published' => (bool) env('LEGAL_PUBLISHED', false),
    'business_name' => env('LEGAL_BUSINESS_NAME'),
    'business_address' => env('LEGAL_BUSINESS_ADDRESS'),
    'contact_email' => env('LEGAL_CONTACT_EMAIL'),
    'privacy_email' => env('LEGAL_PRIVACY_EMAIL'),
    'phone' => env('LEGAL_PHONE'),
    'contact_name' => env('LEGAL_CONTACT_NAME'),
    'facebook_name' => env('LEGAL_FACEBOOK_NAME'),
    'tax_notice' => env('LEGAL_TAX_NOTICE'),
    'providers' => env('LEGAL_PROVIDERS'),
    'retention' => env('LEGAL_RETENTION'),
    'cancellation' => env('LEGAL_CANCELLATION'),
    'refunds' => env('LEGAL_REFUNDS'),
];
