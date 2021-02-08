<?php

namespace Val\Api;

use MaxMind\Db\Reader;

Abstract Class Geolocation
{
    /**
     * Returns an associative array of geolocation data, based on given IP address. 
     * Returns null in case of an error.
     * 
     * Geolocation data array keys:
     *  [city, country, country_code, continent, continent_code, postal_code, time_zone, 
     *  latitude, longitude, accuracy_radius]
     * 
     * Geolocation data provider:
     *  https://www.maxmind.com/
     */
    public static function get(?string $ipAddress = null, ?string $languageCode = null) : ?array
    {
        $reader = new Reader(Config::app('maxmind_db_file'));

        try {
            
            $record = $reader->get($ipAddress ?? $_SERVER['REMOTE_ADDR']);

        } catch(\Exception $e) {

            return null;
        }

        $reader->close();

        $languageCode = $languageCode ?? Config::app('lang_default');
        $lang = in_array($languageCode, ['en', 'ru', 'de', 'es', 'fr',  'ja', 'pt-BR', 'zh-CN']) ? $languageCode : 'en';

        return [
            'city'              => $record['city']['names'][$lang],
            'country'           => $record['country']['names'][$lang],
            'country_code'      => $record['country']['iso_code'],
            'continent'         => $record['continent']['names'][$lang],
            'continent_code'    => $record['continent']['code'],
            'postal_code'       => $record['postal']['code'],
            'time_zone'         => $record['location']['time_zone'],
            'latitude'          => $record['location']['latitude'],
            'longitude'         => $record['location']['longitude'],
            'accuracy_radius'   => $record['location']['accuracy_radius']
        ];
    }

}
