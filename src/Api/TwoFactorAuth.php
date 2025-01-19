<?php

namespace Val\Api;

Abstract Class TwoFactorAuth
{
    const CODE_LENGTH = 6;
    const TIME_WINDOW = 30;
    const CHARSET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * Generates an URI for the TOTP key. This URI may be further encoded into 
     * a QR code and scanned by the Authenticator app.
     * 
     * Examples:
     *   $appName: 'My App', 'Example.com', 'app'
     *   $accountName: 'john@doe.com', 'john_doe', 'John Doe'
     * 
     * Info:
     * https://github.com/google/google-authenticator/wiki/Key-Uri-Format
     */
    public static function createURI(string $secretKey, string $appName, string $accountName) : string
    {
        $issuer = rawurlencode($appName);
        $label = $issuer . ':' . rawurlencode($accountName);

        return "otpauth://totp/{$label}?secret={$secretKey}&issuer={$issuer}
            &algorithm=SHA256";
    }

    /**
     * Generates a TOTP secret key of 32 characters length in Base32. Returns
     * null in case of an error.
     */
    public static function generateSecretKey() : ?string
    {
        try {

            $key = '';
            for ($i = 0; $i < 32; $i++) $key .= self::CHARSET[random_int(0, 31)];

            return $key;

        } catch(\Random\RandomException) {

            error_log('An appropriate source of randomness cannot be found for 
                the random_int() function.');

            return null;
        }
    }

    /**
     * Returns true if the user entered code is correct. To avoid discrepancy 
     * in time, also accepts the corresponding codes of the previous and next
     * time windows.
     */
    public static function verify(string $secretKey, string $code) : bool
    {
        $time = time();
        $result = 0;

        // Safe iteration and code comparison under timing attacks.
        for ($i = -1; $i <= 1; $i++) {

            $timeSlice = floor($time / self::TIME_WINDOW + $i);
            $result = hash_equals(self::getCode($secretKey, $timeSlice), $code)
                ? $timeSlice
                : $result;
        }

        return ($result > 0);
    }

    /**
     * Calculates the code for a given secret key.
     */
    protected static function getCode(string $secretKey, ?int $timeSlice = null) : string
    {
        $time = pack('J', $timeSlice ?? floor(time() / self::TIME_WINDOW));
        $hash = hash_hmac('sha256', $time, self::base32Decode($secretKey), true);
        $hashpart = substr($hash, ord(substr($hash, -1)) & 0x0F, 4);
        $value = unpack('N', $hashpart)[1] & 0x7FFFFFFF;

        return str_pad($value % pow(10, self::CODE_LENGTH), self::CODE_LENGTH, '0', STR_PAD_LEFT);
    }

    /**
     * Decodes a Base32 string (without padding).
     */
    protected static function base32Decode(string $base32) : string
    {
        $buffer = '';

        // Map each character to a group of 5 bits and pass to the buffer.
        foreach (str_split($base32) as $char)
            $buffer .= sprintf('%05b', strpos(self::CHARSET, $char));

        $string = '';

        // Split the buffer into bytes, convert them to ASCII and add 
        // to the string.
        foreach (str_split($buffer, 8) as $byte)
            $string .= chr(bindec(str_pad($byte, 8, '0', STR_PAD_RIGHT)));

        return $string;
    }

}
