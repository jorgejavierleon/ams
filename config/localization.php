<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Supported Locales
    |--------------------------------------------------------------------------
    |
    | The locales the application can be switched to at runtime. The array key
    | is the locale code passed to app()->setLocale(); the value maps it to the
    | BCP 47 tag used by the frontend Intl formatters (date, number, currency).
    |
    | Chile ships first, so `es` (formatted as es-CL) is the default. English is
    | wired end-to-end but its catalogs may be partial until an English rollout
    | is planned. Add a locale here and provide its lang/<code>/ files to enable it.
    |
    */

    'supported' => [
        'es' => 'es-CL',
        'en' => 'en-US',
    ],

    /*
    |--------------------------------------------------------------------------
    | Session Key
    |--------------------------------------------------------------------------
    |
    | Session key under which the user's chosen locale is persisted by the
    | locale switch route and read back by the SetLocale middleware.
    |
    */

    'session_key' => 'locale',

    /*
    |--------------------------------------------------------------------------
    | Shared Translation Namespaces
    |--------------------------------------------------------------------------
    |
    | The lang file groups exposed to the React frontend through Inertia shared
    | props. Keep this limited to UI catalogs — server-side messages such as
    | `validation` and `auth` reach the frontend already resolved via Inertia's
    | `errors` prop, so they do not need to be shipped here.
    |
    */

    'shared_namespaces' => [
        'ui',
    ],

];
