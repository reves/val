<?php

namespace Val\Api;

use Val\App\{HTTP, Auth};

Abstract Class Captcha
{
    /**
     * Verifies the Cloudflare's Turnstile response token and returns an
     * associative array of response data, or null in case of an error.
     * 
     * @link https://developers.cloudflare.com/turnstile/get-started/server-side-validation/
     * Response documentation.
     */
    public static function Turnstile(string $secret, string $response) : ?array
    {
        return HTTP::post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
            'secret' => $secret,
            'response' => $response,
            'remoteip' => Auth::getIPAddress()
        ]);
    }

    /**
     * Verifies the hCaptcha response token and returns an
     * associative array of response data, or null in case of an error.
     * 
     * @link https://docs.hcaptcha.com/#verify-the-user-response-server-side
     * Response documentation.
     */
    public static function hCaptcha(string $secret, string $response, ?string $sitekey = null) : ?array
    {
        return HTTP::post('https://api.hcaptcha.com/siteverify', [
            'secret' => $secret,
            'response' => $response,
            'remoteip' => Auth::getIPAddress(),
            'sitekey' => $sitekey
        ]);
    }

    /**
     * Verifies the Google's reCAPTCHA v3 response token and returns an
     * associative array of response data, or null in case of an error.
     * 
     * @link https://developers.google.com/recaptcha/docs/v3#site_verify_response
     * Response documentation.
     */
    public static function reCAPTCHA(string $secret, string $response) : ?array
    {
        return HTTP::post('https://www.google.com/recaptcha/api/siteverify', [
            'secret' => $secret,
            'response' => $response,
            'remoteip' => Auth::getIPAddress()
        ]);
    }

}
