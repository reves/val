<?php

namespace Val\App;

use PDO;
use Val\App;

enum DBDriver : string
{
    case MySQL      = 'mysql'; // compatible with MariaDB 
    case PostgreSQL = 'pgsql';
    case SQLite     = 'sqlite';
}

Final Class DB
{
    protected static ?self $instance = null;

    // Database driver.
    public static ?DBDriver $driver = null;

    // Data Source Name.
    protected static string $DSN = '';

    // Database handler.
    protected static ?PDO $handler = null;

    // Prepared statement.
    protected static ?\PDOStatement $statement = null;

    // Transaction status.
    protected static bool $transactionActive = false;

    // Counter for the question mark placeholders.
    protected static int $questionMarkPlaceholderIndex = 0;

    protected function __construct() {} // for convenience of using object operator

    /**
     * Initializes the database module. Returns true in case of success, or 
     * false, if the database config is missing.
     * 
     * @throws \LogicException
     */
    public static function init() : bool
    {
        // Already initialized.
        if (self::$instance) 
            return true;

        // Configuration file is missing.
        if (Config::db() === null)
            return false;

        self::$instance = new self;

        // Create a DSN (connection parameters string).
        $driver = Config::db('driver') ?? DBDriver::MySQL;

        if (!$driver instanceof DBDriver) {

            $driver = DBDriver::tryFrom($driver)
                ?? throw new \LogicException('The specified database "driver" is not supported.');
        }

        self::$driver = $driver;
        self::$DSN = $driver->value . ':';

        // SQLite (on-disk only)
        if ($driver === DBDriver::SQLite) {

            $path = Config::db('path');

            if ($path === null)
                throw new \LogicException('The field "path" is not set in the database config.');

            self::$DSN .= $path;

            return true;
        }

        // MySQL and PostgreSQL
        ($host = Config::db('host')) && self::$DSN .= "host={$host};";
        ($port = Config::db('port')) && self::$DSN .= "port={$port};";
        ($db = Config::db('db'))     && self::$DSN .= "dbname={$db};";
        self::$DSN .= ($driver === DBDriver::PostgreSQL)
            ? "options='--client_encoding=UTF8'"
            : 'charset=utf8mb4';

        return true;
    }

    /** 
     * Connects to the database.
     * 
     * @throws \LogicException
     */
    protected static function connect() : PDO
    {
        // Already connected.
        if (self::$handler) 
            return self::$handler;

        if (Config::db() === null)
            throw new \LogicException('Database configuration file is missing.');

        // Connection parameters.
        $user = Config::db('user');
        $pass = Config::db('pass');

        $options = [
            PDO::ATTR_CASE => PDO::CASE_NATURAL,
            PDO::ATTR_ORACLE_NULLS => PDO::NULL_NATURAL,
            PDO::ATTR_STRINGIFY_FETCHES => false,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ];

        if (self::$driver === DBDriver::SQLite) {

            $options[PDO::SQLITE_ATTR_OPEN_FLAGS] = PDO::SQLITE_OPEN_CREATE | PDO::SQLITE_OPEN_READWRITE;

        } else {

            if ($user === null)
                throw new \LogicException('The field "user" is not set in the database config.');

            if ($pass === null)
                throw new \LogicException('The field "pass" is not set in the database config.');

        }

        // Connect.
        try {

            return self::$handler = new PDO(self::$DSN, $user, $pass, $options);

        } catch (\PDOException $e) {

            // Suppress the exception details to prevent exposing sensitive
            // information.
            error_log("Database connection error: {$e->getMessage()}");

            throw new \LogicException('Database connection error, check the logs for details.');

        }
    }

    /**
     * Initiates a new transaction.
     */
    public static function beginTransaction() : bool
    {
        return (self::$transactionActive)
            ? true
            : self::$transactionActive = self::connect()->beginTransaction();
    }

    /**
     * Commits the current transaction.
     */
    public static function commit() : bool
    {
        return (!self::$transactionActive)
            ? true
            : !(self::$transactionActive = !self::$handler->commit());
    }

    /**
     * Cancels the current transaction.
     */
    public static function rollback() : bool
    {
        return (!self::$transactionActive)
            ? true
            : self::$transactionActive = !self::$handler->rollBack();
    }

    /**
     * Verifies that the current transaction is active.
     */
    public static function transactionIsActive() : bool
    {
        return self::$transactionActive;
    }

    /**
     * Executes an SQL statement with a custom query. Data inside the query
     * should be properly escaped. This method cannot be used with any queries
     * that return results. Returns the number of rows that were modified or
     * deleted, or false on error.
     */
    public static function raw(string $query) : int|bool
    {
        try {

            return self::connect()->exec($query);

        } catch (\PDOException) {

            return false;
        }
    }

    /**
     * The id of the last inserted row. Warning(!) In case of a transaction,
     * should be used before commit.
     */
    public static function lastInsertId() : string
    {
        return self::$handler->lastInsertId();
    }
    
    /**
     * Prepares the statement for execution.
     */
    public static function prepare(string $query) : self
    {
        self::$statement = self::connect()->prepare($query);
        self::$questionMarkPlaceholderIndex = 0;

        return self::$instance;
    }

    /**
     * Executes the prepared statement. All the $parameters values are treated
     * as PDO::PARAM_STR. Returns true on success.
     */
    public static function execute(?array $parameters = null) : bool
    {
        return self::$statement->execute($parameters);
    }

    /**
     * Binds the $value to the $placeholder. The $placeholder can be either a
     * string denoting a named placehold or an int denoting a question mark
     * placeholder index in the query.
     */
    public static function bind(string|int $placeholder, bool|float|int|string|null $value) : self
    {
        if (is_int($placeholder))
            self::$questionMarkPlaceholderIndex = $placeholder;

        $type = match (true) {
            is_int($value)  => PDO::PARAM_INT,
            is_bool($value) => PDO::PARAM_BOOL,
            is_null($value) => PDO::PARAM_NULL,
            default         => PDO::PARAM_STR
        };

        self::$statement->bindValue($placeholder, $value, $type);

        return self::$instance;
    }

    /**
     * Binds multiple placeholders using sef::bind method for each placeholder.
     * The $relations parameter should represent an array of $placeholder => 
     * $value relations. Read self::bind method documentation for details.
     */
    public static function bindMultiple(array $relations) : self
    {
        foreach ($relations as $placeholder => $value)
            self::bind($placeholder, $value);

        return self::$instance;
    }

    /**
     * Binds the $value to a question mark placeholder whose index
     * automatically increments.
     */
    public static function bindPlaceholder(bool|float|int|string|null $value) : self
    {
        return self::bind(++self::$questionMarkPlaceholderIndex, $value);
    }

    /**
     * Binds the $values to multiple question mark placeholders whose index 
     * automatically increments using sef::bindPlaceholder method for each of
     * them. Read self::bindPlaceholder method documentation for details.
     */
    public static function bindMultiplePlaceholders(array $values) : self
    {
        foreach ($values as $value) self::bindPlaceholder($value);

        return self::$instance;
    }

    /**
     * Returns the array containing all of the result set rows.
     * 
     * Result example: (using PDO::FETCH_ASSOC)
     * 
     *  [
     *      ["id" => 1, "name" => "banana"],
     *      ["id" => 2, "name" => "apple"]
     *  ]
     * 
     */
    public static function resultset(?array $parameters = null) : array
    {
        self::execute($parameters);

        return self::$statement->fetchAll();
    }

    /**
     * Returns the array containing first row of the result set rows. Returns
     * null in case of an empty result or an error.
     * 
     * Result example: (using PDO::FETCH_ASSOC)
     * 
     *  ["id" => 1, "name" => "banana"]
     * 
     */
    public static function single(?array $parameters = null) : ?array
    {
        self::execute($parameters);
        $row = self::$statement->fetch();
        self::$statement->closeCursor();

        return $row ?: null;
    }

    /**
     * Returns the number of rows affected by the last SQL statement.
     * Warning(!) when PDO::MYSQL_ATTR_FOUND_ROWS is set to true, it returns
     * the number of found (matched) rows, not the number of changed rows.
     */
    public static function rowCount() : int
    {
        return self::$statement->rowCount();
    }

    /**
     * Returns a dateTime string matching the ISO 8601 "YYYY-MM-DD hh:mm:ss"
     * format. If no timestamp given, returns the current dateTime.
     */
    public static function dateTime(?int $timestamp = null) : string
    {
        return date('Y-m-d H:i:s', $timestamp);
    }

    /**
     * Generates a string with $count question mark placeholders in total.
     */
    public static function generatePlaceholders(int $count) : string
    {
        return rtrim(str_repeat('?,', $count), ',');
    }

    /**
     * Closes the connection.
     */
    public static function close() : void
    {
        self::$statement = null;
        self::$handler = null;
    }

    /**
     * Closes connection on destruct.
     */
    public function __destruct()
    {
        self::close();
    }
    
}
