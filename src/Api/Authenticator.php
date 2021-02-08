<?php

namespace Val\Api;

use Val\App\Config;

Abstract Class Authenticator
{
    const CODE_LENGTH = 6;
    const TIME_WINDOW = 30;
    const CHARSET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * Creates a base32 secret key of 16 chars length.
     * 
     * https://github.com/google/google-authenticator
     */
    public static function generateSecretKey() : string
    {
        $secretKey = '';
        for ($i = 0; $i < 16; $i++) $secretKey .= self::CHARSET[random_int(0, 31)];
        return $secretKey;
    }

    /**
     * Returns true if the code is correct. To avoid discrepancy in time, also accepts the 
     * corresponding codes of the previous and next time windows.
     */
    public static function verifyCode(string $secretKey, string $code) : bool
    {
        $time = time();
        $result = 0;

        // Timing attack safe iteration and code comparison
        for ($i = -1; $i <= 1; $i++) {
            $timeSlice = floor($time / self::TIME_WINDOW + $i);
            $result = hash_equals(self::getCode($secretKey, $timeSlice), $code) ? $timeSlice : $result;
        }

        return ($result > 0);
    }

    /**
     * Calculates the code for a given secret key.
     */
    public static function getCode(string $secretKey, ?int $timeSlice = null) : string
    {
        $time = pack('J', $timeSlice ?? floor(time() / self::TIME_WINDOW));
        $hash = hash_hmac('sha1', $time, self::base32Decode($secretKey), true);
        $hashpart = substr($hash, ord(substr($hash, -1)) & 0x0F, 4);
        $value = unpack('N', $hashpart)[1] & 0x7FFFFFFF;

        return str_pad($value % pow(10, self::CODE_LENGTH), self::CODE_LENGTH, '0', STR_PAD_LEFT);
    }

    /**
     * Generates a QR code image URL.
     * 
     * Provider:
     *  http://goqr.me/api/
     */
    public static function getQRCodeUrl(string $secretKey, string $label, int $size = 200) : string
    {
        $issuer = Config::app('authenticator_issuer');
        $urlEncoded = rawurlencode("otpauth://totp/{$label}?secret={$secretKey}&issuer={$issuer}");

        return "https://api.qrserver.com/v1/create-qr-code/?data=$urlEncoded&size=${size}x${size}&ecc=M";
    }

    /**
     * Decodes a base32 (without padding) string.
     */
    protected static function base32Decode(string $base32) : string
    {
        $buffer = '';

        // Map each character to a group of 5 bits and pass to the buffer
        foreach (str_split($base32) as $char) {
            $buffer .= sprintf('%05b', strpos(self::CHARSET, $char));
        }

        $string = '';

        // Split the buffer into bytes, convert them to ASCII and add to the string
        foreach (str_split($buffer, 8) as $byte) {
            $string .= chr(bindec(str_pad($byte, 8, '0', STR_PAD_RIGHT)));
        }

        return $string;
    }

}
