<?php

namespace Val\App;

Abstract Class Crypt
{
    /**
     * Encrypts plaintext $data in a Secret-key Authenticated Encryption way. Returns a 
     * Base64 URL safe no padding encoded string.
     */
    public static function encrypt(string $data) : string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = sodium_crypto_secretbox($data, $nonce, Config::app('key'));
        
        sodium_memzero($data);

        return sodium_bin2base64($nonce . $ciphertext, SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
    }

    /**
     * Decrypts Base64 URL safe no padding encoded $data in a Secret-key Authenticated 
     * Decryption way. Returns decrypted plaintext data or null in case of an error.
     */
    public static function decrypt(string $data) : ?string
    {
        try {

            $decoded = sodium_base642bin($data, SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
            $nonce = mb_substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, '8bit');
            $ciphertext = mb_substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, null, '8bit');
            $plaintext = sodium_crypto_secretbox_open($ciphertext, $nonce, Config::app('key'));
            
        } catch (\SodiumException $e) {

            return null;
        }

        return ($plaintext !== false) ? $plaintext : null;
    }

}
