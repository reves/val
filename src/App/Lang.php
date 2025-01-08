<?php

namespace Val\App;

Abstract Class Lang
{
    const COOKIE_NAME = 'lang';

    // Language code
    protected static ?string $code = null;

    /**
     * Detects the language code from the cookie. If the cookie is not set
     * or has an invalid format, or is not supported (only in case that the
     * supported languages list is specified in the config), then detects the
     * language code from the 'Accept-Language' request header. If the language
     * is still not supported, sets the first language code from the supported
     * languages list. Otherwise unsets the cookie.
     */
    public static function init() : void
    {
        // Already set
        if (self::$code)
            return;

        // Detect from cookie
        if (
            Cookie::isSet(self::COOKIE_NAME) &&
            self::$code = self::parse(Cookie::get(self::COOKIE_NAME))
        ) {
            self::updateCookie();
            return;
        }

        // Detect from 'Accept-Language' header
        if (
            isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) && 
            self::$code = self::parse(strtok(strtok($_SERVER['HTTP_ACCEPT_LANGUAGE'], ','), ';'))
        ) {
            self::updateCookie();
            return;
        }

        // Default supported language
        if (Config::app('languages')) {
            self::$code = Config::app('languages')[0];
            self::updateCookie();
            return;
        }

        Cookie::unset(self::COOKIE_NAME);
    }

    /**
     * Returns the detected language code, or null if the language couldn't be
     * parsed.
     */
    public static function get() : ?string
    {
        return self::$code;
    }

    /**
     * Sets the language code. If an empty string is passed, unsets, or if the 
     * supported languages list is specified, sets to the first one in the
     * list. Returns false, if the language code has an invalid format or an
     * error occurred while setting the cookie, otherwise true.
     */
    public static function set(string $code) : bool
    {
        if ($code) {

            if (!$code = self::parse($code))
                return false;

            self::$code = $code;
            return self::updateCookie();
        }

        if (Config::app('languages')) {
            self::$code = Config::app('languages')[0];
            return self::updateCookie();
        }

        return Cookie::unset(self::COOKIE_NAME);
    }

    /**
     * Returns the language code, if it has a valid format. Checks if it is
     * supported (only in case that the supported languages list is specified
     * in the config). If the code is not valid or not supported, returns null.
     * 
     * Valid format:
     *  <ISO 639 language code>[-<ISO 3166-1 region code>]
     */
    protected static function parse(string $code) : ?string
    {   
        // Invalid format
        if (!preg_match('/^([a-z]{2,3})(?:-[A-Z]{2})?$/', $code, $m))
            return null;

        // Valid format
        if (!Config::app('languages'))
            return $code;

        // Check if the language is supported
        $similar = null;

        foreach (Config::app('languages') as $supportedCode) {
            // Supported language, exact match
            if ($code === $supportedCode)
                return $code;

            // Remember the first similar supported code
            if (!$similar && $m[1] == substr($supportedCode, 0, 2))
                $similar = $supportedCode;
        }

        // Supported language, but not exact match
        if ($similar)
            return $similar;

        // Not supported
        return null;
    }

    /**
     * Helper method to update the value of language cookie.
     */
    protected static function updateCookie() : bool
    {
        return Cookie::setForDays(self::COOKIE_NAME, self::$code, 365, ['httponly' => false]);
    }

}
