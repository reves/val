<?php

namespace Val\Api;

use Val\App\{HTTP, Config};

Abstract Class ReCaptcha
{
    /**
     * Verifies the reCAPTCHA v3 token and returns an associative array of response data. 
     * Returns null in case of an error.
     * 
     * Response data array keys:
     *  [action, score]
     * 
     * See response description:
     *  https://developers.google.com/recaptcha/docs/v3#site_verify_response
     */
    public static function verify(string $token) : ?array
    {
        $result = HTTP::post('https://www.google.com/recaptcha/api/siteverify', [
            'secret' => Config::app('recaptcha_secret'),
            'response' => $token,
            'remoteip' => $_SERVER['REMOTE_ADDR']
        ]);

        if (!$result['success']) {

            return null;
        }

        return [
            'action' => $result['action'],
            'score' => $result['score']
        ];
    }

}
