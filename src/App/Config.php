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
        $manyArguments = isset($arguments[1]);

        if ($manyArguments)
            throw new \LogicException('Only one config field can be specified.');

        // Get the config file (if not cached yet).
        if (!array_key_exists($name, self::$configs)) {
            
            $path = App::$DIR_CONFIG . "/{$name}.php";
            $isDev = false;

            // Environment variables.
            if ($name == 'env') {

                // As the development env variables take precedence, check if
                // the corresponding file exists. If so, set this config by 
                // default for the "::env()" handle.
                $pathDev = App::$DIR_CONFIG . '/env.dev.php';
                $isDev = is_file($pathDev);
                $path = $isDev ? $pathDev : $path;
            }

            if (!$isDev && !is_file($path))
                return null;
            
            // Require and cache the config.
            self::$configs[$name] = require $path;

            if ($isDev) {

                self::$configs[$name]['_DEV_'] = true; // meta data
            }
        }

        // No field specified, return the config itself.
        if (!$arguments)
            return self::$configs[$name];

        // Return the specified config field.
        return self::$configs[$name][$arguments[0]] ?? null;
    }

}
