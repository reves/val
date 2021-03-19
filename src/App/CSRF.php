<?php

namespace Val\App;

Abstract Class CSRF
{
    const COOKIE = 'csrftoken'; // TODO: Consider using cookie prefixes in the future

    /**
     * Sets the CSRF token cookie if not set.
     */
    public static function init() : void
    {
        if (Config::app() === null)
            return;

        if (Cookie::isSet(self::COOKIE) && Crypt::decrypt(Cookie::get(self::COOKIE)))
            return;

        Cookie::set(self::COOKIE, Crypt::encrypt(random_bytes(32)), ['httponly' => false, 'samesite' => 'Strict']);
    }

    /**
     * Returns true if the CSRF token from the cookie matches the CSRF token from the 
     * HTTP header, otherwise returns false.
     */
    public static function tokensMatch() : bool
    {
        $cookie = Cookie::get(self::COOKIE);

        return (
            $cookie && 
            isset($_SERVER['HTTP_X_CSRF_TOKEN']) && 
            Crypt::decrypt($cookie) &&
            $_SERVER['HTTP_X_CSRF_TOKEN'] === $cookie
        );
    }
    
}
