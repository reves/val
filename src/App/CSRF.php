<?php

namespace Val\App;

Abstract Class CSRF
{
    const string COOKIE_NAME = '__Secure-csrftoken';

    /**
     * Sets the CSRF token cookie if not set. Does nothing, if the app config
     * is missing.
     */
    public static function init() : void
    {
        if (Config::app() === null)
            return;

        if (Cookie::isSet(self::COOKIE_NAME) && Crypt::decrypt(Cookie::get(self::COOKIE_NAME)))
            return;

        try {

            Cookie::set(self::COOKIE_NAME, Crypt::encrypt(random_bytes(32)), ['httponly' => false, 'samesite' => 'Strict']);

        } catch (\Random\RandomException) {

            return;
        }
    }

    /**
     * Returns true if the CSRF token from the cookie matches the CSRF token
     * from the HTTP header, otherwise returns false.
     */
    public static function tokensMatch() : bool
    {
        $cookie = Cookie::get(self::COOKIE_NAME);

        return (
            $cookie && 
            isset($_SERVER['HTTP_X_CSRF_TOKEN']) && 
            Crypt::decrypt($cookie) &&
            $_SERVER['HTTP_X_CSRF_TOKEN'] === $cookie
        );
    }
    
}
