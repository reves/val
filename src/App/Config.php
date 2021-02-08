<?php

namespace Val\App;

Abstract Class Config
{
    // Cached in-memory configurations (those that were requested earlier)
    protected static array $configs = [];

    /**
     * Gets the configuration file specified by "method $name" and returns the config 
     * field specified by "first argument $arguments[0]". If the configuration file does 
     * not exist, returns null. If no argument was provided, returns an array of all the 
     * config fields.
     * 
     * @throws \LogicException
     */
    public static function __callStatic(string $name, array $arguments)
    {
        $argumentsCount = count($arguments);

        if ($argumentsCount > 1)
            throw new \LogicException('Only one config field can be specified.');

        $name = strtolower($name);

        if (!array_key_exists($name, self::$configs)) {

            $path = DIR_CONFIG . "/{$name}.config.php";

            if (!is_file($path)) {
                
                return null;
            }

            self::$configs[$name] = require $path;
        }

        if (!$argumentsCount) {

            return self::$configs[$name];
        }

        $field = strtolower($arguments[0]);

        if (!isset(self::$configs[$name][$field]))
            throw new \LogicException("The \"{$field}\" field is not set in the \"{$name}\" configuration.");

        if (self::$configs[$name][$field] === '')
            throw new \LogicException("The \"{$field}\" field value in the \"{$name}\" configuration is empty.");

        return self::$configs[$name][$field];
    }
    
}
