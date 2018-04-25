<?php
return [

    /*
    |--------------------------------------------------------------------------
    | Lexroute setting
    |--------------------------------------------------------------------------
    | Updating settng will not change old route. with command route:update
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Frontpage
    |--------------------------------------------------------------------------
    |
    | Set your frontpage name from route. it will be use for url frontpage with
    | with name frontpage. You can disable it with "[]",
    |
    */

    'frontpage' => [],

    /*
    |--------------------------------------------------------------------------
    | Middleware
    |--------------------------------------------------------------------------
    |
    | Set your middleware for all route.
    | You can disable it with "[]",
    |
    */

    'middleware' => [],

    /*
    |--------------------------------------------------------------------------
    | Api Middleware
    |--------------------------------------------------------------------------
    |
    | Set your middleware for all route.
    | You can disable it with "[]",
    |
    */

    'apimiddleware' => [],

    /*
    |--------------------------------------------------------------------------
    | Restore
    |--------------------------------------------------------------------------
    |
    | Set your middleware for all route.
    | You can disable it with 0,
    |
    */

    'restore' => 0,

    /*
    |--------------------------------------------------------------------------
    | Controller Path
    |--------------------------------------------------------------------------
    |
    | This namespace is applied to your controller routes. by default we use
    | from RouteServiceProvider, You can disable it with false
    |
    */

    'controllerpath' => false,

    /*
    |--------------------------------------------------------------------------
    | Route path
    |--------------------------------------------------------------------------
    */

    'routepath' => 'routes',

   /*
    |--------------------------------------------------------------------------
    | Web Route path
    |--------------------------------------------------------------------------
    */

    'webroute' => 'web',

    /*
    |--------------------------------------------------------------------------
    | Api Route path
    |--------------------------------------------------------------------------
    */

    'apiroute' => 'api',

    /*
    |--------------------------------------------------------------------------
    | Api Controller Path
    |--------------------------------------------------------------------------
    |
    | Set our namescpace for api route. from this point we will break
    | api and web route. creating api must at same directory. like
    | App\Http\Controllers\Child or App\Http\Controllers\Api or
    | App\Http\ApiControllers if we have different RouteServiceProvider for
    | api, You can disable it with false, if disable we can use command -a
    |
    */

    'apicontrollerpath' => false,

    /*
    |--------------------------------------------------------------------------
    | Translations
    |--------------------------------------------------------------------------
    |
    | Set your translations for all route.
    | You can disable it with [],
    | 'translations' => ['en','id'],
    |
    */

    'translations' => [],
];