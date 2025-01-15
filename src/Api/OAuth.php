<?php

namespace Val\Api;

use Val\App\{HTTP, Config};

Abstract Class OAuth
{
    /**
     * Google.
     * Returns null in case of an error.
     * 
     * https://developers.google.com/identity/protocols/OAuth2
     */
    public static function Google(string $code) : ?array
    {
        return HTTP::post('https://www.googleapis.com/oauth2/v4/token', [
            'code' => $code,
            'client_id' => Config::oauth('google_client_id'),
            'client_secret' => Config::oauth('google_client_secret'),
            'redirect_uri' => Config::oauth('google_redirect_uri'),
            'grant_type' => 'authorization_code'
        ]);
    }

    /**
     * Facebook.
     * Returns null in case of an error.
     * 
     * https://developers.facebook.com/docs/facebook-login/manually-build-a-login-flow
     */
    public static function Facebook(string $code) : ?array
    {
        return HTTP::post('https://graph.facebook.com/v4.0/oauth/access_token', [
            'code' => $code,
            'client_id' => Config::oauth('facebook_client_id'),
            'client_secret' => Config::oauth('facebook_client_secret'),
            'redirect_uri' => Config::oauth('facebook_redirect_uri')
        ]);
    }
    
}
