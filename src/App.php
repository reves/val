<?php

namespace Val;

use Val\App\{Lang, CSRF, DB, Auth, Renderer, Config};

/**
 * Application entry point.
 */
Abstract Class App
{
    // App status
    protected static bool $running = false;

    // Whether is production environment
    protected static ?bool $isProd = null;

    // Directory path constants
    public static string $DIR_ROOT;
    public static string $DIR_API;
    public static string $DIR_CONFIG;
    public static string $DIR_MIGRATIONS;
    public static string $DIR_PUBLIC;
    public static string $DIR_RESOURCES;
    public static string $DIR_VIEW;

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
        self::$DIR_API        = self::$DIR_ROOT . '/api';
        self::$DIR_CONFIG     = self::$DIR_ROOT . '/config';
        self::$DIR_MIGRATIONS = self::$DIR_ROOT . '/migrations';
        self::$DIR_PUBLIC     = self::$DIR_ROOT . '/public';
        self::$DIR_RESOURCES  = self::$DIR_ROOT . '/resources';
        self::$DIR_VIEW       = self::$DIR_ROOT . '/view';

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

        if (App::isApi()) {

            // API specific headers
            header('Content-type: application/json; charset=utf-8');

            self::loadApi();
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
     * Loads the requested API endpoint object.
     */
    protected static function loadApi() : Api
    {
        $regex = '/^[a-zA-Z]{1,50}$/';
        $api = $_GET['_api'];

        if (!preg_match($regex, $api))
            return new Api;

        // Get the API Class.
        $className = ucfirst($api);
        $path = self::$DIR_API . "/{$className}.php";

        if (!is_file($path))
            return new Api;

        require $path;

        $className = "\\$className";
        $action = $_GET['_action'];

        // Create the API object and return it back to the App, so the magic
        // method "__invoke()" could be called (meaning the default action).
        if (!$action)
            return new $className;
        
        // If the Action is specified, pass it to the API Class constructor,
        // so it will call the Action and then App::exit().
        return preg_match($regex, $action)
            ? new $className($action)
            : new Api;
    }

    /**
     * Verifies that the contents of a variable can be called as a function
     * from App's scope (outside the API classes). It is used to check if any
     * of the API's method is public.
     */
    public static function _isCallable($var) : bool
    {
        return is_callable($var);
    }

    /**
     * Checks if the current request is an API request.
     */
    public static function isApi() : bool
    {
        return isset($_GET['_api']);
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
        
        $env = Config::env();

        if (!$env)
            throw new \LogicException('Environment configuration file is
                missing.');

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

}
