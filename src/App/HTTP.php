<?php

namespace Val\App;

Abstract Class HTTP
{
    /**
     * Sends a GET request. Returns an array of JSON decoded data or null, in case of an 
     * error or empty response.
     */
    public static function get(string $url, array $parameters = []) : ?array
    {
        $result = file_get_contents("{$url}?" . http_build_query($parameters));

        return $result ? JSON::decode($result) : null;
    }

    /**
     * Sends a POST request. Returns an array of JSON decoded data or null, in case of an 
     * error or empty response.
     */
    public static function post(string $url, array $parameters = []) : ?array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => [
                    'Content-Type: application/x-www-form-urlencoded',
                    'Accept: application/json'
                ],
                'content' => http_build_query($parameters)
            ]
        ]);

        $result = file_get_contents($url, false, $context);

        return $result ? JSON::decode($result) : null;
    }

}