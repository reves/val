<?php

namespace Val\Api;

use MaxMind\Db\Reader;
use Val\App\Config;

Abstract Class Geolocation
{
    protected static array $languages = ['en', 'ru', 'de', 'es', 'fr',  'ja', 'pt-BR', 'zh-CN'];
    /**
     * Returns an associative array of geolocation data, based on given IP address. 
     * Returns null in case of an error.
     * 
     * Geolocation data array keys:
     *  [city, country, country_code, continent, continent_code, postal_code, time_zone]
     * 
     * Database: GeoLite2 City
     * Provider: https://dev.maxmind.com/geoip/geoip2/geolite2/
     * License: https://www.maxmind.com/en/geolite2/eula
     */
    public static function get(?string $ipAddress = null, ?string $lang = null) : ?array
    {
        $reader = new Reader(Config::app('maxmind_db_file'));

        try {

            $record = $reader->get($ipAddress ?? $_SERVER['REMOTE_ADDR']);

        } catch(\Exception $e) {

            return null;
        }

        $reader->close();

        if ($record === null) {
                
            return null;
        }

        $lang = $lang ?? Lang::get();
        $lang = in_array($lang, self::$languages) ? $lang : self::$languages[0];

        return [
            'city'              => $record['city']['names'][$lang] ?? '',
            'country'           => $record['country']['names'][$lang] ?? '',
            'country_code'      => $record['country']['iso_code'] ?? '',
            'continent'         => $record['continent']['names'][$lang] ?? '',
            'continent_code'    => $record['continent']['code'] ?? '',
            'time_zone'         => $record['location']['time_zone'] ?? ''
        ];
    }

}
