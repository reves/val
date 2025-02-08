<?php

namespace Val\App;

use Val\App;

Abstract Class Config
{
    // Cached in-memory configurations (requested earlier).
    protected static array $configs = [];

    /**
     * Gets the configuration file specified by dynamic method $name and
     * returns the value of the config field specified in the string argument
     * $arguments[0]. If the configuration file does not exist, returns null.
     * If the config field is not set, throws an exception. If no argument
     * was provided, returns the config array itself.
     * 
     * @throws \LogicException
     */
    public static function __callStatic(string $name, array $arguments) : mixed
    {
        if (isset($arguments[1]))
            throw new \LogicException('Only one config field can be specified.');

        // Load the config file if not cached yet.
        if (!array_key_exists($name, self::$configs)) self::loadConfig($name);

        return $arguments
            ? (self::$configs[$name][$arguments[0]] ?? null)
            : (self::$configs[$name] ?? null); // No field specified, return the whole config.
    }

    /**
     * Loads and caches the configuration file specified by $name.
     */
    private static function loadConfig(string $name) : void
    {
        $path = App::$DIR_CONFIG . "/{$name}.php";

        // Environment config (environment variables) requested.
        if ($name === 'env') {

            // Check if the development env config file exists. If so, assign
            // this config to the "::env()" handle, as it takes precedence.
            $pathDev = App::$DIR_CONFIG . '/env.dev.php';

            if (is_file($pathDev)) {
                self::$configs[$name] = require $pathDev;
                self::$configs[$name]['_DEV_'] = true; // meta data
                return;
            }
        }

        // Load the config file.
        if (is_file($path)) self::$configs[$name] = require $path;
    }

}
