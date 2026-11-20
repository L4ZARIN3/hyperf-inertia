<?php

declare(strict_types=1);
/**
 * This file is part of the Inertia library for Hyperf.
 *
 * @license  https://github.com/onix-systems-php/hyperf-inertia/blob/main/LICENSE
 */
use function Hyperf\Support\env;

/*
 * This file is part of the extension library for Hyperf.
 *
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
return [
    /*
    |--------------------------------------------------------------------------
    | Root view
    |--------------------------------------------------------------------------
    |
    | The root template that's loaded on the first page visit. It must be
    | located under the `layouts` directory of your configured view paths.
    |
    */
    'root_view' => 'inertia',

    /*
    |--------------------------------------------------------------------------
    | Paths
    |--------------------------------------------------------------------------
    |
    | `public_path_prefix` is the absolute filesystem path to the directory
    | where Vite writes its build output (manifest.json, hot file, assets).
    |
    | `asset_public_path` is the public URL prefix used when generating asset
    | URLs in production.
    |
    */
    'public_path_prefix' => __DIR__ . '/../../storage/public',
    'asset_public_path' => env('DOMAIN_API', '') . '/public',

    /*
    |--------------------------------------------------------------------------
    | Request skipping
    |--------------------------------------------------------------------------
    |
    | Requests whose path starts with one of the `skip_url_prefix` prefixes
    | are not handled by the Inertia middleware. `no_skip_extra_path` allows
    | you to define exceptions: paths listed here are always handled, even
    | when their prefix is skipped.
    |
    */
    'skip_url_prefix' => empty(env('INERTIA_SKIP_URL_PREFIX')) ? [] : explode(',', env('INERTIA_SKIP_URL_PREFIX')),
    'no_skip_extra_path' => empty(env('INERTIA_NO_SKIP_EXTRA_PATH')) ? [] : explode(',', env('INERTIA_NO_SKIP_EXTRA_PATH')),

    /*
    |--------------------------------------------------------------------------
    | Security
    |--------------------------------------------------------------------------
    */
    'is_secure' => (bool) env('INERTIA_IS_SECURE', true),

    /*
    |--------------------------------------------------------------------------
    | Server Side Rendering
    |--------------------------------------------------------------------------
    |
    | These options configures if and how Inertia uses Server Side Rendering
    | to pre-render the initial visits made to your application's pages.
    |
    | You can specify a custom SSR bundle path, or omit it to let Inertia
    | try and automatically detect it for you.
    |
    | Do note that enabling these options will NOT automatically make SSR work,
    | as a separate rendering service needs to be available. To learn more,
    | please visit https://inertiajs.com/server-side-rendering
    |
    */
    'ssr' => [
        'enabled' => (bool) env('INERTIA_SSR_ENABLED', false),
        'url' => env('INERTIA_SSR_URL', 'http://127.0.0.1:13714'),
        // 'bundle' => BASE_PATH . '/bootstrap/ssr/ssr.mjs',
    ],

    /*
    |--------------------------------------------------------------------------
    | Testing
    |--------------------------------------------------------------------------
    |
    | The values described here are used to locate Inertia components on the
    | filesystem. For instance, when using `assertInertia`, the assertion
    | attempts to locate the component as a file relative to any of the
    | paths AND with any of the extensions specified here.
    |
    */
    'testing' => [
        'ensure_pages_exist' => false,

        'page_paths' => [
            BASE_PATH . '/storage/inertia/js/Pages',
        ],

        'page_extensions' => [
            'js',
            'jsx',
            'svelte',
            'ts',
            'tsx',
            'vue',
        ],
    ],
];
