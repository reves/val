<?php

namespace Val\App;

use Val\App;
use PDO;

Class Database
{
    // Data Source Name
    protected string $DSN;

    // Database handler.
    protected PDO $handler;

    // Prepared statement
    protected \PDOStatement $statement;

    // Transaction status
    protected bool $transactionActive = false;

    // Counter for the question mark placeholders
    protected int $questionMarkPlaceholderIndex = 0;

    /**
     * Initializes connection parameters.
     */
    public function __construct()
    {
        $host = Config::db('host');
        $port = Config::db('port');
        $charset = Config::db('charset');
        $dbname = Config::db('dbname');

        $this->DSN = "mysql:host={$host};port={$port};charset={$charset};dbname={$dbname}";
    }

    /**
     * Closes connection on destruct.
     */
    public function __destruct()
    {
        $this->close();
    }

    /** 
     * Connects to the database.
     */
    protected function connect() : self
    {
        if ($this->handler) return $this;

        $options = [
            PDO::ATTR_CASE => PDO::CASE_NATURAL,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_ORACLE_NULLS => PDO::NULL_TO_STRING,
            PDO::ATTR_STRINGIFY_FETCHES => true,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_FOUND_ROWS => true,
        ];

        try {

            $this->handler = new PDO($this->DSN, Config::db('user'), Config::db('pass'), $options);

        } catch (\PDOException $e) {

            error_log("Database connection error: {$e->getMessage()}");
            App::exit();
        }

        return $this;
    }

    /**
     * Initiates a new transaction.
     */
    public function beginTransaction() : self
    {
        if (!$this->transactionActive)
            $this->transactionActive = $this->connect()->handler->beginTransaction();

        return $this;
    }

    /**
     * Commits the current transaction.
     */
    public function endTransaction() : self
    {
        if ($this->transactionActive)
            $this->transactionActive = !$this->handler->commit();

        return $this;
    }

    /**
     * Cancels the current transaction.
     */
    public function cancelTransaction() : self
    {
        if ($this->transactionActive)
            $this->transactionActive = !$this->handler->rollBack();

        return $this;
    }

    /**
     * Verifies that the current transaction is active.
     */
    public function transactionIsActive() : bool
    {
        return $this->transactionActive;
    }

    /**
     * Executes an SQL statement with a custom query. Data inside the query should be 
     * properly escaped.
     */
    public function executeCustom(string $query) : self
    {
        $this->handler->exec($query);

        return $this;
    }

    /**
     * The id of the last inserted row. Warning(!) In case of a transaction, should be 
     * used before commit.
     */
    public function lastInsertId() : string
    {
        return $this->handler->lastInsertId();
    }
    
    /**
     * Prepares the statement for execution.
     */
    public function prepare(string $query) : self
    {
        $this->connect()->statement = $this->handler->prepare($query);

        $this->questionMarkPlaceholderIndex = 0;

        return $this;
    }

    /**
     * Executes the prepared statement. All the $parameters values are treated as 
     * PDO::PARAM_STR.
     */
    public function execute(?array $parameters = null) : self
    {
        $this->statement->execute($parameters);

        return $this;
    }

    /**
     * Binds the $value to the $placeholder. The $placeholder can be either a string 
     * denoting a named placehold or an int denoting a question mark placeholder index 
     * in the query.
     */
    public function bind($placeholder, $value) : self
    {
        if (is_int($placeholder))
            $this->questionMarkPlaceholderIndex = $placeholder;

        switch (true) {

            case is_int($value):
                $type = PDO::PARAM_INT;
                break;

            case is_bool($value):
                $type = PDO::PARAM_BOOL;
                break;

            case is_null($value):
                $type = PDO::PARAM_NULL;
                break;

            default:
                $type = PDO::PARAM_STR;

        }

        $this->statement->bindValue($placeholder, $value, $type);

        return $this;
    }

    /**
     * Binds multiple placeholders using sef::bind method for each of them. The 
     * $relations should represent an array of $placeholder => $value relations. 
     * Read self::bind method documentation for details.
     */
    public function bindMultiple(array $relations) : self
    {
        foreach ($relations as $placeholder => $value)
            $this->bind($placeholder, $value);

        return $this;
    }

    /**
     * Binds the $value to a question mark placeholder whose index automatically 
     * increments.
     */
    public function bindPlaceholder($value) : self
    {
        return $this->bind(++$this->placeholderIndex, $value);
    }

    /**
     * Binds the $values to multiple question mark placeholders whose index 
     * automatically increments using sef::bindPlaceholder method for each of them. 
     * Read self::bindPlaceholder method documentation for details.
     */
    public function bindMultiplePlaceholders(array $values) : self
    {
        foreach ($values as $value)
            $this->bindPlaceholder($value);

        return $this;
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
    public function resultset(?array $parameters = null) : array
    {
        $this->execute($parameters);
        return $this->statement->fetchAll();
    }

    /**
     * Gets the array containing first row of the result set rows.
     * 
     * Result example: (using PDO::FETCH_ASSOC)
     * 
     *  ["id" => 1, "name" => "banana"]
     * 
     */
    public function single(?array $parameters = null) : array
    {
        $this->execute($parameters);
        $row = (array)$this->statement->fetch();
        $this->statement->closeCursor();

        return $row;
    }

    /**
     * Gets the number of rows affected by the last SQL statement. Warning(!) when 
     * PDO::MYSQL_ATTR_FOUND_ROWS is set to true, it gets the number of found (matched) 
     * rows, not the number of changed rows.
     */
    public function rowCount() : int
    {
        return $this->statement->rowCount();
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
    public static function generatePlaceholders($count) : string
    {
        return rtrim(str_repeat('?,', $count), ',');
    }

    /**
     * Closes the connection.
     */
    public function close() : void
    {
        unset($this->statement, $this->handler);
    }
    
}
