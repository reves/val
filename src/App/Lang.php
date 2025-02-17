<?php

namespace Val\App;

use Val\App;

Abstract Class Lang
{
    const string COOKIE_NAME = 'lang';

    // Language code
    protected static ?string $code = null;

    /**
     * Detects the user preferred language. Manages the language code in the 
     * URL path.
     */
    public static function init() : void
    {
        // Already set
        if (self::$code)
            return;

        // Detect language code
        self::detect();

        // Handle language code in the URL path:

        if (App::isApiRequest() || !Config::app('language_in_url') || !Config::app('languages'))
            return;

        $URI = $_SERVER["REQUEST_URI"] ?? '/';
        $codeInPath = strtok($URI, '/');
        $parsed = self::parse($codeInPath);

        // Language code is not present in the URL path, so insert the detected 
        // language code.
        if ($parsed === null) {
            header('Location: /' . self::$code . ($URI === '/' ? '' : $URI), true, 302);
            return;
        }

        // Language code is present in the URL path, but not supported, so 
        // replace it with the detected language code.
        //  ||
        // Language code in the URL path is supported, but doesn't correspond 
        // to the detected one (user preferred), or is similar to the detected 
        // one, but not exact (e.g. different region), so replace it with the 
        // detected one.
        if ($parsed === '' || $codeInPath != self::$code) {
            header('Location: ' . substr_replace($URI, self::$code, 1, strlen($codeInPath)), true, 302);
            return;
        }
    }

    /**
     * Detects the language code from the cookie. If the cookie is not set
     * or has an invalid format, or is not supported (only in case that the
     * supported languages list is specified in the config), then detects the
     * language code from the 'Accept-Language' request header. If the language
     * is still not supported, sets the first language code from the supported
     * languages list. Otherwise unsets the cookie.
     */
    protected static function detect() : bool
    {
        // From cookie.
        if (Cookie::isSet(self::COOKIE_NAME) &&
            self::$code = self::parse(Cookie::get(self::COOKIE_NAME))
        ) {
            return self::updateCookie();
        }

        // From 'Accept-Language' header.
        $code = strtok($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '', ',');
        while ($code) {
            if (self::$code = self::parse(explode(';', $code, 2)[0])) 
                return self::updateCookie();
            $code = strtok(',');
        }

        // Default supported language.
        if (Config::app('languages')) {
            self::$code = Config::app('languages')[0];
            return self::updateCookie();
        }

        return Cookie::unset(self::COOKIE_NAME);
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
     * in the config). Returns null if the code is not valid, or en empty 
     * string if it is not supported.
     * 
     * Valid format:
     *  <ISO 639 language code>[-<ISO 3166-1 region code>]
     */
    protected static function parse(string|bool $code) : ?string
    {
        // Invalid format
        if (!$code || !preg_match('/^([a-z]{2,3})(?:-[A-Z]{2})?$/', $code, $m))
            return null;

        // Valid format
        if (!Config::app('languages'))
            return $code;

        // Check if the language is supported
        $similar = '';

        foreach (Config::app('languages') as $supportedCode) {
            // Supported language, exact match
            if ($code === $supportedCode)
                return $code;

            // Remember the first similar supported code
            if (!$similar && $m[1] == substr($supportedCode, 0, 2))
                $similar = $supportedCode;
        }

        // Either supported language, but not exact match, or Not supported.
        return $similar;
    }

    /**
     * Helper method to update the value of language cookie.
     */
    protected static function updateCookie() : bool
    {
        return Cookie::setForDays(self::COOKIE_NAME, self::$code, 365, ['httponly' => false]);
    }

}
