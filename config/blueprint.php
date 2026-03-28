<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Supported Locales
    |--------------------------------------------------------------------------
    | The locales that the SetLocale middleware will accept. Any locale not
    | in this list will be ignored and the app default will be used instead.
    */
    'supported_locales' => ['en', 'ar'],

    /*
    |--------------------------------------------------------------------------
    | Auto Publish
    |--------------------------------------------------------------------------
    | When true the package will attempt to copy config and lang files into
    | the consuming application automatically if they are missing. This can
    | be disabled by setting `blueprint.auto_publish` to false in the app.
    */
    'auto_publish' => true,

];