<?php

namespace Val\App;

Abstract Class Lang
{
    const COOKIE = 'lang';

    // Language code
    protected static ?string $code = null;

    /**
     * Detects the language code from the cookie. If the cookie is not set or is in an 
     * invalid format, detects the language code from the 'Accept-Language' request header 
     * and sets the cookie.
     */
    public static function init() : void
    {
        if (self::$code)
            return;

        if (Cookie::isSet(self::COOKIE) && self::$code = self::parse(Cookie::get(self::COOKIE)))
            return;

        if (
            isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) && 
            self::$code = self::parse(strtok(strtok($_SERVER['HTTP_ACCEPT_LANGUAGE'], ','), ';'))
        ) {
            Cookie::setForDays(self::COOKIE, self::$code, 365, ['httponly' => false]);
            return;
        }

        Cookie::unset(self::COOKIE);
    }

    /**
     * Returns the detected language code, or null if the language could not be parsed.
     */
    public static function get() : ?string
    {
        return self::$code;
    }

    /**
     * Returns the language code if it is in a valid format, otherwise returns null.
     * Valid format: [ISO 639 language code]-[ISO 3166-1 region code]
     */
    protected static function parse(string $code) : ?string
    {
        return preg_match('/^[a-z]{2,3}(?:-[A-Z]{2})?$/', $code) ? $code : null;
    }

}
