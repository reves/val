<?php

/**
 * Production environment settings.
 */
return [

    /**
     * Application secret key.
     * 
     * Used in "config/app.php"
     */
    'app_key' => '{key}',

    /**
     * Database connection parameters.
     * 
     * Used in "config/db.php"
     */
    'db_host' => '127.0.0.1',
    'db_name' => 'database',
    'db_user' => 'root',
    'db_pass' => '',

    // 'db_path' => Val\App::$DIR_ROOT.'/db.sqlite3',

];
