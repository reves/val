<?php

namespace Val\App;

use DateTime;

Abstract Class Token
{
    const TIME_SECONDS = 'seconds';
    const TIME_MINUTES = 'minutes';
    const TIME_HOURS = 'hours';
    const TIME_DAYS = 'days';

    /**
     * Creates a new token by encoding data in JSON format and encrypting it.
     * Returns null in case of an error.
     */
    public static function create(array $data) : ?string
    {
        return Crypt::encrypt(JSON::encode($data));
    }

    /**
     * Extracts data from the token by decrypting it and decoding from JSON
     * format. Returns null in case of an error.
     */
    public static function extract(string $token) : ?array
    {
        return $token ? JSON::decode(Crypt::decrypt($token)) : null;
    }

    /**
     * Checks if a token has expired based on its creation time and time to 
     * live (TTL). The time scale for TTL must be specified using one of the
     * class constants.
     * 
     * @throws \InvalidArgumentException
     */
    public static function expired(string $createdAt, int $timeToLive, string $timeScale) : bool
    {
        $diffInSeconds = time() - strtotime($createdAt);

        switch ($timeScale) {

            case (self::TIME_SECONDS):
                return ($diffInSeconds >= $timeToLive);

            case (self::TIME_MINUTES):
                return ($diffInSeconds >= $timeToLive * 60);

            case (self::TIME_HOURS):
                return ($diffInSeconds >= $timeToLive * 3600);

            case (self::TIME_DAYS):
                return ($diffInSeconds >= $timeToLive * 86400);
        }

        throw new \InvalidArgumentException('The "$timeScale" parameter must be 
            one of the predefined class constants.');
    }
}
