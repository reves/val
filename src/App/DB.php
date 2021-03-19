<?php

namespace Val\App;

use Val\App;
use PDO;

Final Class DB
{
    protected static ?self $instance = null;

    // Data Source Name
    protected static string $dsn = '';

    // Database handler.
    protected static ?PDO $handler = null;

    // Prepared statement
    protected static ?\PDOStatement $statement = null;

    // Transaction status
    protected static bool $transactionActive = false;

    // Counter for the question mark placeholders
    protected static int $questionMarkPlaceholderIndex = 0;

    /**
     * Initializes connection parameters.
     */
    protected function __construct()
    {
        $driver = Config::db('driver') ?? 'mysql';
        self::$dsn = "{$driver}:";

        if ($driver === 'sqlite') {
            $path = Config::db('path');
            $memory = Config::db('memory');
            self::$dsn .= $path ?? ($memory ? ':memory:' : '' );

            return;
        }

        ($host = Config::db('host')) && self::$dsn .= "host={$host};";
        ($port = Config::db('port')) && self::$dsn .= "port={$port};";
        ($db = Config::db('db'))     && self::$dsn .= "dbname={$db};";
        ($user = Config::db('user')) && self::$dsn .= "user={$user};";
        ($pass = Config::db('pass')) && self::$dsn .= "password={$pass};";

        self::$dsn .= ($driver === 'pgsql') ? 'options=\'--client_encoding=UTF8\'' : 'charset=utf8mb4';
    }

    /**
     * Initializes the database module. Returns null if the configuration file is missing.
     */
    public static function init() : ?self
    {
        return (Config::db() === null) ? null : self::$instance ?? self::$instance = new self;
    }

    /** 
     * Connects to the database.
     * 
     * @throws \RuntimeException
     */
    protected static function connect() : self
    {
        if (self::$handler) return self::$instance;

        if (Config::db() === null)
            throw new \RuntimeException('Database configuration file is missing.');

        $options = [
            PDO::ATTR_CASE => PDO::CASE_NATURAL,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_ORACLE_NULLS => PDO::NULL_NATURAL,
            PDO::ATTR_STRINGIFY_FETCHES => false,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ];

        try {

            self::$handler = new PDO(self::$dsn, options: $options);

        } catch (\PDOException $e) {

            error_log("Database connection error: {$e->getMessage()}");
            App::exit();
        }

        return self::$instance;
    }

    /**
     * Initiates a new transaction.
     */
    public static function beginTransaction() : bool
    {
        return (self::$transactionActive) ? true : self::$transactionActive = self::connect()::$handler->beginTransaction();
    }

    /**
     * Commits the current transaction.
     */
    public static function commit() : bool
    {
        return (!self::$transactionActive) ? true : self::$transactionActive = !self::$handler->commit();
    }

    /**
     * Cancels the current transaction.
     */
    public static function rollback() : bool
    {
        return (!self::$transactionActive) ? true : self::$transactionActive = !self::$handler->rollBack();
    }

    /**
     * Verifies that the current transaction is active.
     */
    public static function transactionIsActive() : bool
    {
        return self::$transactionActive;
    }

    /**
     * Executes an SQL statement with a custom query. Data inside the query should be 
     * properly escaped. This method cannot be used with any queries that return results.
     */
    public static function raw(string $query) : self
    {
        self::connect()::$handler->exec($query);

        return self::$instance;
    }

    /**
     * The id of the last inserted row. Warning(!) In case of a transaction, should be 
     * used before commit.
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
        self::$statement = self::connect()::$handler->prepare($query);
        self::$questionMarkPlaceholderIndex = 0;

        return self::$instance;
    }

    /**
     * Executes the prepared statement. All the $parameters values are treated as 
     * PDO::PARAM_STR. Returns true on success.
     */
    public static function execute(?array $parameters = null) : bool
    {
        return self::$statement->execute($parameters);
    }

    /**
     * Binds the $value to the $placeholder. The $placeholder can be either a string 
     * denoting a named placehold or an int denoting a question mark placeholder index 
     * in the query.
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
     * Binds multiple placeholders using sef::bind method for each placeholder. The 
     * $relations should represent an array of $placeholder => $value relations. 
     * Read self::bind method documentation for details.
     */
    public static function bindMultiple(array $relations) : self
    {
        foreach ($relations as $placeholder => $value)
            self::bind($placeholder, $value);

        return self::$instance;
    }

    /**
     * Binds the $value to a question mark placeholder whose index automatically 
     * increments.
     */
    public static function bindPlaceholder(bool|float|int|string|null $value) : self
    {
        return self::bind(++self::$questionMarkPlaceholderIndex, $value);
    }

    /**
     * Binds the $values to multiple question mark placeholders whose index 
     * automatically increments using sef::bindPlaceholder method for each of them. 
     * Read self::bindPlaceholder method documentation for details.
     */
    public static function bindMultiplePlaceholders(array $values) : self
    {
        foreach ($values as $value)
            self::bindPlaceholder($value);

        return self::$instance;
    }

    /**
     * Gets the array containing all of the result set rows.
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
     * Gets the array containing first row of the result set rows. Returns null in case of 
     * an empty result or an error.
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
     * Gets the number of rows affected by the last SQL statement. Warning(!) when 
     * PDO::MYSQL_ATTR_FOUND_ROWS is set to true, it gets the number of found (matched) 
     * rows, not the number of changed rows.
     */
    public static function rowCount() : int
    {
        return self::$statement->rowCount();
    }

    /**
     * Gets the current dateTime matching the MySQL "YYYY-MM-DD hh:mm:ss" format.
     */
    public static function dateTime() : string
    {
        return date('Y-m-d H:i:s');
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
