<?php

namespace Val\Api;

use Val\App\Config;

Abstract Class Lang
{
    // Cached lang code
    private static string $code = '';

    /**
     * Returns the language code parsed from the request header 'Accept-Language'. In case 
     * of an error, returns the default setting from the app config.
     */
    public static function get() : string
    {
        if (self::$code) {

            return self::$code;
        }

        if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) && $code = self::acceptable(substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2))) {
            
            return self::$code = $code;
        }

        return self::$code = 'en';
    }

    /**
     * Returns the language code lowercased, if it consists of two letters [a-z], 
     * otherwise returns null.
     */
    public static function acceptable(string $code) : ?string
    {
        $code = strtolower($code);

        return preg_match('/[a-z]{2}/', $code) ? $code : null;
    }

}
