<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Hook Manager Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration options for the WordPress-style hook management system.
    |
    */

    'hooks' => [
        /*
        |--------------------------------------------------------------------------
        | Enable Hook Logging
        |--------------------------------------------------------------------------
        |
        | When enabled, all hook registrations and executions will be logged
        | to the Laravel log. This is useful for debugging but may impact
        | performance in production.
        |
        */
        'enable_logging' => env('CORE_SERVICES_HOOK_LOGGING', false),

        /*
        |--------------------------------------------------------------------------
        | Hook Error Handling
        |--------------------------------------------------------------------------
        |
        | When enabled, hook execution errors will throw exceptions instead
        | of being silently logged. This is useful for debugging but should
        | be disabled in production to prevent one hook failure from breaking
        | the entire execution flow.
        |
        */
        'throw_on_error' => env('CORE_SERVICES_HOOK_THROW_ON_ERROR', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Plugin Management Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration options for the plugin management system.
    | (To be implemented in future phases)
    |
    */

    'plugins' => [
        /*
        |--------------------------------------------------------------------------
        | Plugin Directory
        |--------------------------------------------------------------------------
        |
        | The directory where plugins are stored. This should be relative to
        | the application's base path.
        |
        */
        'directory' => env('CORE_SERVICES_PLUGIN_DIRECTORY', 'plugins'),

        /*
        |--------------------------------------------------------------------------
        | Auto-discover Plugins
        |--------------------------------------------------------------------------
        |
        | When enabled, plugins will be automatically discovered and loaded
        | from the plugin directory on application boot.
        |
        */
        'auto_discover' => env('CORE_SERVICES_PLUGIN_AUTO_DISCOVER', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Plugin Path (Alias)
    |--------------------------------------------------------------------------
    |
    | Convenience alias for the plugin path.
    |
    */
    'plugins_path' => env('PLUGINS_PATH', base_path('plugins')),
];

