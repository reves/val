<?php

namespace Val\App;

use DateTime;

Abstract Class Token
{
    const EXPIRED_DAYS = 'days';
    const EXPIRED_HOURS = 'hours';
    const EXPIRED_MINUTES = 'minutes';

    /**
     * Creates a new token by encoding $data in JSON format and encrypting it. Returns 
     * null in case of an error.
     */
    public static function create(array $data) : ?string
    {
        $dataEncoded = JSON::encode($data);

        return ($dataEncoded === null) ? null : Crypt::encrypt($dataEncoded);
    }

    /**
     * Extracts data from the $token by decrypting it and decoding from JSON format. 
     * Returns null in case of an error.
     */
    public static function extract(string $token) : ?array
    {
        $dataEncoded = Crypt::decrypt($token);

        if ($dataEncoded === null) {

            return null;
        }

        return JSON::decode($dataEncoded) ?? null;
    }

    /**
     * Checks if a token has expired based on its creation time and time to live (TTL). 
     * The time scale for TTL must be specified using a class constant.
     * 
     * @throws \InvalidArgumentException
     */
    public static function expired(string $createdAt, int $timeToLive, string $timeScale) : bool
    {
        $diff = (new DateTime($createdAt))->diff(new DateTime());

        switch ($timeScale) {
            case (self::EXPIRED_DAYS):
                return ($diff->days >= $timeToLive);

            case (self::EXPIRED_HOURS):
                $diffHours = ($diff->days * 24) + $diff->h;
                return ($diffHours >= $timeToLive);

            case (self::EXPIRED_MINUTES):
                $diffMinutes = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i;
                return ($diffMinutes >= $timeToLive);
        }

        throw new \InvalidArgumentException('The "string $timeScale" parameter must be one of the predefined class constants.');
    }
}