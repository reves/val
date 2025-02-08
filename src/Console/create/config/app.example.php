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

];
