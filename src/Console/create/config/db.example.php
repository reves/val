<?php

use Val\App\{DBDriver, Config};

/**
 * Database connection settings.
 * 
 * Config required for modules: DB, Auth.
 */
return [
    
    /**
     * Supported drivers: MySQL (MariaDB), PostgreSQL and SQLite.
     * 
     * Default: Val\App\DBDriver::MySQL
     */
    'driver' => DBDriver::MySQL,

    /**
     * MySQL (MariaDB) or PostgreSQL
     */
    'host' => Config::env('db_host'),
    'db'   => Config::env('db_name'),
    'user' => Config::env('db_user'),
    'pass' => Config::env('db_pass'),

    /**
     * SQLite
     */
    // 'path' => Config::env('db_path'),

];
