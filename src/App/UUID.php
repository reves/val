<?php

namespace Val\App;

Abstract Class UUID
{
    // Timestamp of the previously generated UUID.
    private static int $lastTime = 0;

    /**
     * Generates a UUID Version 7 (RFC 9562). Returns null in case of an error.
     * 
     * Inspired by: https://github.com/oittaa/uuid-php
     */
    public static function generate() : ?string
    {
        // Timestamp in the format of <seconds + milliseconds>
        $time = intval((new \DateTimeImmutable('now'))->format('Uv'));

        // The new UUID should not be generated in the same millisecond as the
        // previous one, so we increment the timestamp if necessary.
        if ($time == self::$lastTime) {
            $time = self::$lastTime + 1;
        }

        // Generate random data.
        try {

            $data = random_bytes(10);

        } catch (\Random\RandomException) {

            return null;
        }

        // Set the version and variant bits.
        $data[0] = chr((ord($data[0]) & 0x0f) | 0x70);
        $data[2] = chr((ord($data[2]) & 0x3f) | 0x80);

        // Update the last timestamp.
        self::$lastTime = $time;

        // Create UUID
        return vsprintf(
            '%s%s-%s-%s-%s-%s%s%s',
            str_split(
                str_pad(dechex($time), 12, '0', \STR_PAD_LEFT)
                    . bin2hex($data),
                4
            )
        );
    }

}
