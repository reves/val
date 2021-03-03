<?php

namespace Val\App;

Abstract Class Crypt
{
    /**
     * Encrypts a message in a Secret-key Authenticated Encryption way. Returns a Base64 
     * URL safe no padding encoded string.
     */
    public static function encrypt(string $message) : string
    {
        $nonce = random_bytes(\SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $encryptedMessage  = sodium_crypto_secretbox($message, $nonce, self::getSecretKey());

        return sodium_bin2base64($nonce . $encryptedMessage , \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
    }

    /**
     * Decrypts Base64 URL safe no padding encoded encrypted message in a Secret-key 
     * Authenticated Decryption way. Returns the decrypted message or null in case of an 
     * error.
     */
    public static function decrypt(string $encodedEncryptedMessage) : ?string
    {
        try {

            $decoded = sodium_base642bin($encodedEncryptedMessage, \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
            $nonce = mb_substr($decoded, 0, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, '8bit');
            $encryptedMessage = mb_substr($decoded, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, null, '8bit');
            $decryptedMessage  = sodium_crypto_secretbox_open($encryptedMessage, $nonce, self::getSecretKey());

        } catch (\SodiumException) {

            return null;
        }

        return ($decryptedMessage !== false) ? $decryptedMessage  : null;
    }

    /**
     * Returns the application secret key.
     */
    protected static function getSecretKey() : string
    {
        return sodium_base642bin(Config::app('key'), \SODIUM_BASE64_VARIANT_ORIGINAL_NO_PADDING); // TODO: check if the config field is set
    }

}
