<?php

namespace Val\App;

Abstract Class JSON
{
    /**
     * Returns a string containing the JSON representation of the supplied data array, 
     * or null in case of error. Detailed information on encoding options is available 
     * at https://riptutorial.com/php/example/2019/encoding-a-json-string.
     * Warning (!) it is desirable that the data array be associative.
     */
    public static function encode(array $data) : ?string
    {
        try {

            $json = json_encode($data, 
                JSON_THROW_ON_ERROR |
                JSON_INVALID_UTF8_IGNORE | 
                JSON_UNESCAPED_UNICODE | 
                JSON_UNESCAPED_LINE_TERMINATORS | 
                JSON_UNESCAPED_SLASHES | 
                JSON_PRESERVE_ZERO_FRACTION
            );

        } catch(\JsonException $e) {

            error_log($e->getMessage());
            return null;
        }

        return $json;
    }
    
    /**
     * Returns an array of data decoded from the supplied JSON string, or null in case 
     * of error.
     */
    public static function decode(?string $json) : ?array
    {
        if (!$json) {

            return null;
        }

        try {

            $data = json_decode($json, true, 512, 
                JSON_THROW_ON_ERROR |
                JSON_INVALID_UTF8_IGNORE | 
                JSON_BIGINT_AS_STRING
            );
        
        } catch(\JsonException $e) {

            error_log($e->getMessage());
            return null;
        }

        return $data;
    }

}
