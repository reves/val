<?php

namespace Val;

use Val\App\{Config, CSRF, Database, Auth, Renderer};

Class App
{
    // Database module
    protected Database $db;

    // Auth module
    protected Auth $auth;

    // Renderer module
    protected Renderer $renderer;

    /**
     * Initiates the necessary modules for the application. The order of initiation 
     * matters, as some modules use the functionality of others.
     */
    public function __construct(?callable $view = null)
    {
        CSRF::init();

        if (Config::db() !== null) {

            $this->db = new Database;

            if (Config::account() !== null) {

                $this->auth = new Auth($this->db);
                
            }
        }

        $this->renderer = new Renderer;

        // Call the main View function.
        if ($view !== null) {
            
            $view->bindTo($this, $this)();
            
        }
    }

    /**
     * Generates a URL for the specified path using the application host name.
     */
    final public static function buildUrlTo(string $path) : string
    {
        $path = trim(trim($path), '/');

        return "https://{$_SERVER['SERVER_NAME']}/{$path}";
    }

    /**
     * Exits the application.
     */
    final public static function exit() : void
    {
        exit;
    }

}
