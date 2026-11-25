<?php

return [
    // Hostname only; APP_URL supplies the scheme and port for platform links.
    'platform_domain' => strtolower(env('PLATFORM_DOMAIN', parse_url(env('APP_URL', 'http://localhost'), PHP_URL_HOST) ?: 'localhost')),
];
