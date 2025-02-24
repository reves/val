<?php

use Val\App\Config;

/**
 * Application settings.
 * 
 * Config required to run the application.
 */
return [

    /**
     * Application secret key.
     */
    'key' => Config::env('app_key'),

    /**
     * Supported languages. The first language code in the list is the default
     * one.
     */
    'languages' => [
        'en',
    ],

    /**
     * Whether to set the language code in the URL path.
     * E.g. example.com/en, example.com/en/page.
     * 
     * Default: false
     */
    'language_in_url' => false,

];
