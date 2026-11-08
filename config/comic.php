<?php

$defaultSources = 'https://manhwa18.cc/,https://manga18fx.com/,https://asurascans.com/';
$rawSources = env('COMIC_SOURCES', env('COMIC_SOURCE_URL', $defaultSources));
$sourcesArray = array_values(array_filter(array_map('trim', explode(',', $rawSources))));

if (empty($sourcesArray)) {
    $sourcesArray = ['https://manhwa18.cc/'];
}

return [
    /*
    |--------------------------------------------------------------------------
    | Comic Sources (Supports single or multiple comma-separated URLs)
    |--------------------------------------------------------------------------
    */
    'sources' => $sourcesArray,
    'source_url' => $sourcesArray[0] ?? 'https://manhwa18.cc/',

    /*
    |--------------------------------------------------------------------------
    | Cookie / Cloudflare cf_clearance
    |--------------------------------------------------------------------------
    | If a target site uses Cloudflare (like allporncomic.com), paste your
    | browser's Cookie header here (e.g. cf_clearance=...;).
    */
    'cookie' => env('COMIC_COOKIE', ''),

    /*
    |--------------------------------------------------------------------------
    | Cache Settings
    |--------------------------------------------------------------------------
    */
    'cache_ttl' => (int) env('COMIC_CACHE_TTL', 3600),

    /*
    |--------------------------------------------------------------------------
    | User Agent
    |--------------------------------------------------------------------------
    */
    'user_agent' => env(
        'COMIC_USER_AGENT',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36'
    ),

    /*
    |--------------------------------------------------------------------------
    | Enable Image Proxy
    |--------------------------------------------------------------------------
    */
    'proxy_images' => env('COMIC_PROXY_IMAGES', true),

    /*
    |--------------------------------------------------------------------------
    | Timeout for HTTP requests (seconds)
    |--------------------------------------------------------------------------
    */
    'timeout' => (int) env('COMIC_HTTP_TIMEOUT', 15),
];
