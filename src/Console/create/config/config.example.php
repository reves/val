<?php

use Val\App\Config;

/**
 * Example config.
 */
return [

    'field_one' => 'value',
    'field_two' => Config::env('env_value'),

];
