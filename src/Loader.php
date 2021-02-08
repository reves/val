<?php

namespace Val;

/**
 * Application entry point. Should be called from the public folder "/index.php".
 * 
 * Usage example:
 * 
 *  <?php
 *  require 'path/to/val/.../src/Loader.php';
 *  Val\Loader::start(function() { echo 'Hello, World!'; });
 * 
 */
Abstract Class Loader
{
    // Application load status
    protected static bool $loaded = false;

    /**
     * Starts the application by loading the View or the requested API.
     */
    final public static function start(callable $view) : void
    {
        if (self::$loaded) return;

        self::$loaded = true;

        date_default_timezone_set('UTC');

        define('DIR_VAL_MODULES', __DIR__ . '/../modules');
        define('DIR_ROOT',      getcwd() . '/../');
        define('DIR_CONFIG',    DIR_ROOT . '/config');
        define('DIR_API',       DIR_ROOT . '/api');
        define('DIR_TEMPLATES', DIR_ROOT . '/templates');

        require DIR_VAL_MODULES . '/MaxMind-DB-Reader-php/autoload.php';
        require 'App/Config.php';
        require 'App/CSRF.php';
        require 'App/JSON.php';
        require 'App/HTTP.php';
        require 'App/Database.php';
        require 'App/Device.php';
        require 'App/Auth.php';
        require 'App/Cookie.php';
        require 'App/Renderer.php';
        require 'App/Encryption.php';
        require 'App/Token.php';
        require 'App.php';

        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');

        if (isset($_GET['_api'])) {

            require 'Api/Lang.php';
            require 'Api/Mail.php';
            require 'Api/OAuth.php';
            require 'Api/Geolocation.php';
            require 'Api/ReCaptcha.php';
            require 'Api/Authenticator.php';
            require 'Api.php';

            header('Content-type: application/json; charset=utf-8');

            self::loadApi();

        } else {

            header('Content-Type: text/html; charset=utf-8');
            header('X-XSS-Protection: 1; mode=block');

            new App($view);

        }
    }

    /**
     * Loads and calls the requested API's method.
     */
    final protected static function loadApi() : Api
    {
        $apiClassName = ucfirst($_GET['_api']) . 'Api';
        $path = DIR_API . "/{$apiClassName}.php";

        if (is_file($path)) {

            require $path;

            if (isset($_GET['_action'])) {
                
                return new $apiClassName($_GET['_action']);
            }

            return new $apiClassName;
        }

        return new Api;
    }

    /**
     * Verifies that the contents of a variable can be called as a function from 
     * Loader's scope (outside the Application classes). Useful for checking if any 
     * API's method is public.
     */
    final public static function isCallable($var) : bool
    {
        return is_callable($var);
    }

}
