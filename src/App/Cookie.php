<?php

namespace Val\App;

Abstract Class Cookie
{
    /**
     * Verifies that the cookie specified by $name is set.
     */
    public static function isSet(string $name) : bool
    {
        return isset($_COOKIE[$name]);
    }

    /**
     * Gets the cookie specified by $name. Returns an empty string if the cookie is 
     * not set.
     */
    public static function get(string $name) : string
    {
        return $_COOKIE[$name] ?? '';
    }

    /**
     * Sets a cookie. Returns false in case of an error.
     */
    public static function set(string $name, string $value = '', array $options = []) : bool
    {
        $options = [
            'expires' => $options['expires'] ?? 0,
            'path' => $options['path'] ?? '/',
            'domain' => $options['domain'] ?? $_SERVER['SERVER_NAME'] ?? '',
            'secure' => $options['secure'] ?? true,
            'httponly' => $options['httponly'] ?? true,
            'samesite' => $options['samesite'] ?? 'Lax'
        ];
        
        return setcookie($name, $value, $options);
    }

    /**
     * Deletes the cookie specified by $name. Returns false in case of an error.
     */
    public static function unset(string $name) : bool
    {
        return self::set($name, '', ['expires' => 'Thu, 01 Jan 1970 00:00:00 GMT']);
    }

    /**
     * Sets a cookie that expire in $days.
     * 
     * @throws \InvalidArgumentException
     */
    public static function setForDays(string $name, string $value = '', int $days = 1, array $options = []) : bool
    {
        if ($days < 1)
            throw new \InvalidArgumentException('"int $days" must be greater than or equal to 1.');

        $options['expires'] = time() + $days * 86400;

        return self::set($name, $value, $options);
    }

    /**
     * Sets a cookie that expires in $minutes.
     * 
     * @throws \InvalidArgumentException
     */
    public static function setForMinutes(string $name, string $value = '', int $minutes = 1, array $options = []) : bool
    {
        if ($minutes < 1) 
            throw new \InvalidArgumentException('"int $minutes" must be greater than or equal to 1.');

        $options['expires'] = time() + $minutes * 3600;

        return self::set($name, $value, $options);
    }

    /**
     * Sets a cookie that expires in $seconds.
     * 
     * @throws \InvalidArgumentException
     */
    public static function setForSeconds(string $name, string $value = '', int $seconds = 1, array $options = []) : bool
    {
        if ($seconds < 1)
            throw new \InvalidArgumentException('"int $seconds" must be greater than or equal to 1.');

        $options['expires'] = time() + $seconds * 3600;

        return self::set($name, $value, $options);
    }

}
