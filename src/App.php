<?php

namespace Val;

use Val\App\{Lang, CSRF, DB, Auth, Renderer, Config};

/**
 * Application entry point.
 */
Abstract Class App
{
    // App status.
    protected static bool $running = false;

    // Whether is production environment.
    protected static ?bool $isProd = null;

    // Whether the current request is an API request.
    protected static ?bool $isApiRequest = null;

    // Directory path constants.
    public static string $DIR_ROOT;
    public static string $DIR_PUBLIC;
    public static string $DIR_CONFIG;
    public static string $DIR_API;
    public static string $DIR_VIEW;
    public static string $DIR_MIGRATIONS;

    /**
     * Starts the application by loading the View or the requested API.
     */
    public static function run(?\Closure $view = null, ?string $rootPath = null) : void
    {
        if (self::$running)
            return;

        self::$running = true;

        // Directories
        self::$DIR_ROOT       = $rootPath ?? ($_SERVER['DOCUMENT_ROOT']
                                        ? ($_SERVER['DOCUMENT_ROOT'] . '/..')
                                        : getcwd());
        self::$DIR_PUBLIC     = self::$DIR_ROOT . '/public';
        self::$DIR_CONFIG     = self::$DIR_ROOT . '/config';
        self::$DIR_API        = self::$DIR_ROOT . '/api';
        self::$DIR_VIEW       = self::$DIR_ROOT . '/view';
        self::$DIR_MIGRATIONS = self::$DIR_ROOT . '/migrations';

        // Error reporting
        if (self::isProd()) {

            ini_set('display_errors', 0);
        }

        // Default timezone
        date_default_timezone_set('UTC');

        // Common headers
        header('Cache-Control: no-store, must-revalidate');
        header('Strict-Transport-Security: max-age=31536000'); // info: https://hstspreload.org/
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');

        // Initialize modules
        Lang::init();
        CSRF::init();
        DB::init();
        Auth::init();
        Renderer::init();

        if (App::isApiRequest()) {

            // API specific headers
            header('Content-type: application/json; charset=utf-8');

            Api::_load();

            return;
        }

        // View specific headers
        header('Content-Type: text/html; charset=utf-8');
        header('Referrer-Policy: origin, strict-origin');
        header('X-XSS-Protection: 1; mode=block');

        if ($view) {

            // (!) It is recommended to set 'Content-Security-Policy' header
            // in the view function.
            $view();
        }
    }

    /**
     * Checks if the current request is an API request.
     */
    public static function isApiRequest() : bool
    {
        return self::$isApiRequest ??= isset($_GET['_api']);
    }

    /**
     * Returns true if this is a production environment.
     * 
     * @throws \LogicException
     */
    public static function isProd() : bool
    {
        if (self::$isProd !== null)
            return self::$isProd; // cached value
        
        $env = Config::env() ?: throw new \LogicException('Environment 
            configuration file is missing.');

        return self::$isProd = !isset($env['_DEV_']);
    }

    /**
     * Exits the application.
     */
    public static function exit() : never
    {
        DB::close();
        exit;
    }

    /**
     * Verifies that the contents of a variable can be called as a function
     * from outside the API object context (to check if any of the API's method
     * is public, or if the API object itself is callable).
     */
    public static function _isCallable($var) : bool
    {
        return is_callable($var);
    }

}
