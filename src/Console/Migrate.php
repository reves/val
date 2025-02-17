<?php

namespace Val\Console;

use Val\{App, Console};
use Val\App\{Config, DB, DBDriver};

Abstract Class Migrate
{
    /**
     * Runs migrations to the latest version.
     * 
     * @param $version The version to apply.
     */
    public static function handle(?string $version = null) : bool
    {
        // Validate the verison number syntax.
        if ($version !== null && !preg_match('/^[1-9]\d*$/', $version)) {
            Console::println("Invalid version number \"{$version}\": must contain only digits and not be equal to zero.", Console::ERROR);
            return false;
        }

        // Get the list of migration files.
        $migrationsFiles = self::_getMigrations();

        if (!self::migrationsFilesExist($migrationsFiles))
            return false;

        // Parse the version to migrate to.
        $latestAvailableVersion = array_key_last($migrationsFiles);
        $newVersion = $version ? intval($version) : $latestAvailableVersion;

        // Get the current migration version from the database.
        self::init();

        $currentVersion = DB::prepare(match (DB::$driver) {
            DBDriver::MySQL =>
                'SELECT `Version` FROM `migrations` ORDER BY `Version` DESC',
            DBDriver::PostgreSQL,
            DBDriver::SQLite =>
                'SELECT "Version" FROM "migrations" ORDER BY "Version" DESC'
        })->single()['Version'] ?? 0;

        // Check the necessity of migration.
        if ($version) { // version is specified

            if ($newVersion > $latestAvailableVersion) {
                Console::println("The migration file with version \"{$newVersion}\" not found: the latest found version is \"{$latestAvailableVersion}\".", Console::ERROR);
                return false;
            }

            if ($newVersion < $currentVersion) {
                Console::println("The newer version \"{$currentVersion}\" is already live.", Console::DEBUG);
                return true;
            }

            if ($newVersion == $currentVersion) {
                Console::println("The version \"{$newVersion}\" is the latest and is already live.", Console::DEBUG);
                return true;
            }

        } else if ($newVersion <= $currentVersion) {
            Console::println('The latest migration version is already live.', Console::DEBUG);
            return true;
        }

        // Attempt to migrate.
        Console::println(
            !$currentVersion
                ? "Running all the migrations till the version \"{$newVersion}\":"
                : "Migrating from the current version \"{$currentVersion}\" to the new version \"{$newVersion}\":",
            Console::WARNING,
            count: 2
        );
        Console::println('Continue? (y/n)');

        if (!Console::getUserConfirmation()) {
            Console::println('Migration aborted.', Console::DEBUG);
            return true;
        }

        foreach (self::_getMigrations() as $ver => $migration) {

            // Skip the older versions and the current one.
            if ($ver <= $currentVersion) continue;

            Console::println("Running the migration \"{$migration['fileName']}\".", Console::DEBUG);

            $name = ucfirst($migration['name']);
            $className = "\\{$name}";

            if (class_exists($className)) {
                Console::println("Migration step aborted: the migration class \"{$name}\" has already been declared in this migration batch. Please ensure migrations have unique class names (or migrate step by step).", Console::ERROR);
                return false;
            }

            require App::$DIR_MIGRATIONS."/{$migration['fileName']}";

            try {

                $result = (new $className)->up();

            } catch (\Exception $e) {

                Console::println("Migration stopped: unexpected error while running \"up()\" in the \"{$migration['fileName']}\":", Console::ERROR, 2);
                echo $e->getMessage();
                return false;
            }

            if ($result === false) {
                Console::println("Migration stopped: (bool)\"false\" returned from \"up()\" in the \"{$migration['fileName']}\"!", Console::ERROR);
                return false;
            }

            Console::println("The method \"up()\" ran successfully for the migration version \"{$ver}\".", Console::DEBUG);

            $result = DB::prepare(match (DB::$driver) {
                DBDriver::MySQL =>
                    'INSERT INTO `migrations`
                    VALUES (:ver, :name_, :migratedAt)',
                DBDriver::PostgreSQL, DBDriver::SQLite =>
                    'INSERT INTO "migrations"
                    VALUES (:ver, :name_, :migratedAt)'
            })->bindMultiple([
                ':ver' => $ver,
                ':name_' => $migration['name'],
                ':migratedAt' => DB::dateTime()
            ])->execute();

            if (!$result) {
                Console::println("Migration stopped: failed to register the version \"{$ver}\" in the migrations table!", Console::ERROR);
                return true;
            }

            Console::println("Migrated to the version \"{$ver}\" successfully.", Console::SUCCESS, 2);

            // Migration process completed.
            if ($ver == $newVersion) break;
        }

        Console::println("All the migrations till version \"{$newVersion}\" succesfully completed.", Console::SUCCESS);
        return true;
    }

    /**
     * Rollbacks the last migration.
     * 
     * @param $version The version number to rollback to.
     */
    public static function rollback(?string $version = null) : bool
    {
        // Validate the verison number syntax.
        if ($version !== null && !preg_match('/^\d+$/', $version)) {
            Console::println("Invalid version number \"{$version}\": must contain only digits.", Console::ERROR);
            return false;
        }

        // Get the list of migration files.
        $migrationsFiles = self::_getMigrations(true);

        if (!self::migrationsFilesExist($migrationsFiles))
            return false;

        // Get the list of migrations registered in the database.
        self::init();

        $dbMigrations = DB::prepare(match (DB::$driver) {
            DBDriver::MySQL =>
                'SELECT * FROM `migrations` ORDER BY `Version` DESC',
            DBDriver::PostgreSQL,
            DBDriver::SQLite =>
                'SELECT * FROM "migrations" ORDER BY "Version" DESC'
        })->resultset();

        if (!$dbMigrations) {
            Console::println('No migrations found in the database.', Console::DEBUG);
            return true;
        }

        $currentVersion = $dbMigrations[0]['Version'];
        $previousVersion = $dbMigrations[1]['Version'] ?? 0;

        // Parse the version to rollback to. The rollback version may be "0", 
        // meaning to revert all the migrations.
        $rollbackVersion = $version !== null ? intval($version) : $previousVersion;
        $latestAvailableVersion = array_key_first($migrationsFiles);

        // Check if the exact rollback version is registered in the database.
        if ($rollbackVersion) {
            $found = false;

            foreach ($dbMigrations as $migration) {
                if ($migration['Version'] === $rollbackVersion) {
                    $found = true; break;
                }
            }

            if (!$found) {
                Console::println("The migration with the exact version \"{$rollbackVersion}\" was not found in the database.", Console::ERROR);
                return false;
            }
        }

        // Check the necessity of rollback.
        if ($version !== null) { // version is specified

            if ($rollbackVersion > $currentVersion) {
                Console::println("Unable to rollback to the \"{$rollbackVersion}\" version: an earlier version \"{$currentVersion}\" is already live.", Console::DEBUG);
                return true;
            }

            if ($rollbackVersion == $currentVersion) {
                Console::println("The version \"{$currentVersion}\" is already live.", Console::DEBUG);
                return true;
            }

            if ($rollbackVersion > $latestAvailableVersion) {
                Console::println("The migrations files until version \"{$rollbackVersion}\" were not found: the latest found version is \"{$latestAvailableVersion}\".", Console::ERROR);
                return false;
            }
        }

        // Attempt to rollback.
        Console::println(
            $rollbackVersion
                ? "Rolling back all the migrations until the version \"{$rollbackVersion}\":"
                : 'Reverting all migrations:',
            Console::WARNING,
            count: 2
        );
        Console::println('Continue? (y/n)');

        if (!Console::getUserConfirmation()) {
            Console::println('Rollback aborted.', Console::DEBUG);
            return true;
        }

        foreach ($dbMigrations as $migration) { // sorted descending
            $ver = $migration['Version'];

            // Rollback process completed.
            if ($rollbackVersion == $ver) {
                Console::println("Rollback to the version \"{$rollbackVersion}\" successfully completed.", Console::SUCCESS);
                return true;
            }

            Console::println("Rolling back the version \"{$ver}\".");

            if (!isset($migrationsFiles[$ver])) {
                Console::println("Rollback step aborted: the migration file for the version \"{$ver}\" is missing!", Console::ERROR);
                return false;
            }

            $fileName = $migrationsFiles[$ver]['fileName'];
            $name = ucfirst($migration['Name']);
            $className = "\\{$name}";

            if (class_exists($className)) {
                Console::println("Rollback step aborted: the migration class \"{$name}\" has already been declared in this rollback batch. Please ensure migrations have unique class names (or rollback step by step).", Console::ERROR);
                return false;
            }

            require App::$DIR_MIGRATIONS."/{$fileName}";

            try {

                $result = (new $className)->down();

            } catch (\Exception $e) {

                Console::println("Rollback stopped: unexpected error while running \"down()\" in the \"{$migration['fileName']}\":", Console::ERROR, 2);
                echo $e->getMessage();
                return false;
            }

            if ($result === false) {
                Console::println("Rollback stopped: (bool)\"false\" returned from \"down()\" in the \"{$migration['fileName']}\"!", Console::ERROR);
                return false;
            }

            Console::println("The method \"down()\" ran successfully for the migration version \"{$ver}\".", Console::DEBUG);

            $result = DB::prepare(match (DB::$driver) {
                DBDriver::MySQL =>
                    'DELETE FROM `migrations` WHERE `Version` = :ver',
                DBDriver::PostgreSQL, DBDriver::SQLite =>
                    'DELETE FROM "migrations" WHERE `Version` = :ver'
            })->bind(':ver', $ver)->execute();

            if (!$result || !DB::rowCount()) {
                Console::println("Rollback stopped: failed to delete the version \"{$ver}\" from the migrations table!", Console::ERROR);
                return false;
            }

            Console::println("Rolled back the version \"{$ver}\" successfully.", Console::SUCCESS, 2);
        }

        Console::println('All migrations successfully reverted.', Console::SUCCESS);
        return true;
    }

    /**
     * Checks if the migrations files exist and prints an error message if they
     * don't. Returns false in case of an error.
     */
    protected static function migrationsFilesExist(?array $migrationsFiles) : bool
    {
        if ($migrationsFiles === null) {
            Console::println('Failed to retrieve migrations files: the "migrations" directory might not exist.', Console::ERROR);
            return false;
        }

        if (!$migrationsFiles) {
            Console::println('No migrations found in the "migrations" directory.', Console::ERROR);
            return false;
        }

        return true;
    }

    /**
     * Initializes the database connection and makes sure that the "migrations"
     * table exists and can be used.
     */
    protected static function init() : void
    {
        // Initialize db connection.
        if (!DB::init() || Config::db() === null) {
            Console::println('Migration aborted: database configuration file is missing!', Console::ERROR);
            exit(1);
        }

        // Check if the "migraitons" table exists.
        $result = DB::prepare(match (DB::$driver) {

            DBDriver::MySQL =>
                "SHOW TABLES LIKE 'migrations'",

            DBDriver::PostgreSQL =>
                "SELECT 1 FROM pg_tables
                WHERE schemaname = 'public'AND tablename = 'migrations'",

            DBDriver::SQLite =>
                "SELECT name FROM sqlite_master
                WHERE type='table' AND name='migrations'"

        })->single();

        if ($result)
            return;

        // Create the "migrations" table in the database.
        $result = DB::raw(match (DB::$driver) {

            DBDriver::MySQL =>
                'CREATE TABLE `migrations` (
                    `Version` int unsigned NOT NULL,
                    `Name` varchar(255) NOT NULL,
                    `MigratedAt` datetime NOT NULL,
                    PRIMARY KEY (`Version`))',

            DBDriver::PostgreSQL =>
                'CREATE TABLE "migrations" (
                    "Version" integer NOT NULL PRIMARY KEY,
                    "Name" varchar(255) NOT NULL,
                    "MigratedAt" timestamp NOT NULL)',

            DBDriver::SQLite => 
                'CREATE TABLE "migrations" (
                    "Version" integer NOT NULL PRIMARY KEY,
                    "Name" text NOT NULL,
                    "MigratedAt" text NOT NULL)'
        });

        if ($result === false) {
            Console::println('Migration aborted: failed to create the "migrations" table!', Console::ERROR);
            exit(1);
        }

        Console::println('Table "migrations" successfully created.', Console::SUCCESS, 2);
    }

    /**
     * Returns the list of migration files sorted in ascending order. For 
     * descending order, set the $desc parameter to true. Returns null in case 
     * of an error or if the migrations folder doesn't exist. Exits the
     * application with an error if two migration files with the same version 
     * are found.
     */
    public static function _getMigrations(bool $desc = false) : ?array
    {
        $list = is_dir(App::$DIR_MIGRATIONS)
            ? scandir(App::$DIR_MIGRATIONS, $desc ? \SCANDIR_SORT_DESCENDING : 0)
            : false;

        if ($list === false)
            return null;

        $migrations = [];

        foreach ($list as $fileName) {
            if (!preg_match('/^[1-9]\d*?_[a-zA-Z]+\.php$/', $fileName)) continue;
            ['version' => $ver, 'name' => $name] = self::_parseData($fileName);

            if (isset($migrations[$ver])) {
                Console::println("Error: found two migration files with the same version: \"{$migrations[$ver]['fileName']}\" and \"{$fileName}\".", Console::ERROR);
                exit(1);
            }

            $migrations[$ver] = [
                'fileName' => $fileName,
                'name' => $name
            ]; // resulting array schema
        }

        return $migrations;
    }

    /**
     * Returns an array containing the the version number and the name, parsed 
     * from the migraiton's file name.
     */
    protected static function _parseData(string $fileName) : array
    {
        $parts = explode('_', $fileName, 2);
        return [
            'version' => intval($parts[0]),
            'name' => substr($parts[1], 0, -4) // removes the ".php" suffix
        ];
    }

}
