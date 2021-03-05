<?php

namespace Val;

use Val\App\{CSRF, DB, Auth, Renderer};

/**
 * Application entry point. Should be called from "public/index.php".
 * 
 * Usage example:
 * 
 *  <?php
 *  require __DIR__.'/../vendor/autoload.php';
 *  Val\App::run(function() { echo 'Hello, World!'; });
 * 
 */
Abstract Class App
{
    // App status
    protected static bool $running = false;

    // Directory path constants
    public static string $DIR_ROOT;
    public static string $DIR_API;
    public static string $DIR_CONFIG;
    public static string $DIR_MIGRATIONS;
    public static string $DIR_PUBLIC;
    public static string $DIR_RESOURCES;
    public static string $DIR_TEMPLATES;

    /**
     * Starts the application by loading the View or the requested API.
     */
    public static function run(?\Closure $view = null, ?string $rootPath = null) : void
    {
        if (self::$running) {

            return;
        }

        require __DIR__.'/../vendor/autoload.php';

        self::$running = true;
        self::$DIR_ROOT         = $rootPath ?? ($_SERVER['DOCUMENT_ROOT'] ? $_SERVER['DOCUMENT_ROOT'].'/..' : getcwd());
        self::$DIR_API          = self::$DIR_ROOT . '/api';
        self::$DIR_CONFIG       = self::$DIR_ROOT . '/config';
        self::$DIR_MIGRATIONS   = self::$DIR_ROOT . '/migrations';
        self::$DIR_PUBLIC       = self::$DIR_ROOT . '/public';
        self::$DIR_RESOURCES    = self::$DIR_ROOT . '/resources';
        self::$DIR_TEMPLATES    = self::$DIR_ROOT . '/templates';

        date_default_timezone_set('UTC');
        header('Cache-Control: no-store, must-revalidate');
        header('Strict-Transport-Security: max-age=300'); // Details: https://hstspreload.org/
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');

        CSRF::init();
        DB::init();
        Auth::init();
        Renderer::init();

        if (isset($_GET['_api'])) {

            header('Content-type: application/json; charset=utf-8');
            self::loadApi();

            return;
        }

        header('Content-Type: text/html; charset=utf-8');
        header('Referrer-Policy: origin, strict-origin');
        header('X-XSS-Protection: 1; mode=block');

        if ($view) {

            $view(); // It is recommended to use 'Content-Security-Policy'

        }
    }

    /**
     * Loads and calls the requested API endpoint (& method).
     */
    protected static function loadApi() : Api
    {
        $className = ucfirst($_GET['_api']);
        $path = self::$DIR_API . "/{$className}.php";

        if (is_file($path)) {

            require $path;
            $className = "\\$className";

            return isset($_GET['_action']) ? new $className($_GET['_action']) : new $className;
        }

        return new Api;
    }

    /**
     * Verifies that the contents of a variable can be called as a function from 
     * App's scope (outside the API classes). Useful for checking if any API's method is 
     * public.
     */
    public static function _isCallable($var) : bool
    {
        return is_callable($var);
    }

    /**
     * Generates a URL for the specified path using the application host name.
     */
    public static function buildUrlTo(string $path) : string
    {
        $path = trim(trim($path), '/');

        return "https://{$_SERVER['SERVER_NAME']}/{$path}";
    }

    /**
     * Exits the application.
     */
    public static function exit() : void
    {
        DB::close();
        exit;
    }

}
