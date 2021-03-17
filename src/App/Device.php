<?php

namespace Val\App;

Abstract Class Device
{
    /**
     * Parses user agent information. The User-Agent request header is used by default. 
     * Returns an associative array of string values, each 63 chars max length. Each value 
     * will be empty in case of an error or impossibility to parse.
     * 
     * Device data array keys:
     *  [type, platform, browser]
     * 
     * Database used: 'browscap.ini'
     *  https://www.php.net/manual/en/function.get-browser.php
     */
    public static function get(?string $userAgent = null) : array
    {
        $data = @get_browser($userAgent, true);

        return [
            'type' =>       (!$data || $data['device_type'] == 'unknown') ? '' : mb_substr($data['device_type'], 0, 63),
            'platform' =>   (!$data || $data['platform'] == 'unknown') ? '' : mb_substr($data['platform'], 0, 63),
            'browser' =>    (!$data || $data['browser'] == 'unknown') ? '' : mb_substr($data['browser'], 0, 63)
        ];
    }

}
