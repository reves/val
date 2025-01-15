<?php

namespace Val\Console;

use Val\{App, Console};
use Val\App\{Config, DB, DBDriver};

Abstract Class Migrate
{	
    // TODO: Rollback last migration(s) [+bulk]; to a specific version number? (steps currently)
    // TODO: migrate:status
    // TODO: ask for confirmation before executing

    /**
     * Initializes the database migrations table.
     */
    protected static function init() : void
    {
        if (Config::db() === null) {
            Console::println('Migration aborted! Database configuration file is missing.', '31');
            App::exit();
        }

        $result = DB::raw(match (DB::$driver) {

            DBDriver::MySQL =>
                'CREATE TABLE IF NOT EXISTS `migrations` (
                    `Version` int unsigned NOT NULL,
                    `Name` varchar(255) NOT NULL,
                    `CreatedAt` datetime NOT NULL,
                    PRIMARY KEY (`Version`)
                );',

            DBDriver::PostgreSQL =>
                'CREATE TABLE IF NOT EXISTS "migrations" (
                    "Version" integer NOT NULL PRIMARY KEY,
                    "Name" varchar(255) NOT NULL,
                    "CreatedAt" timestamp NOT NULL
                );',

            DBDriver::SQLite => 
                'CREATE TABLE IF NOT EXISTS "migrations" (
                    "Version" integer NOT NULL PRIMARY KEY,
                    "Name" text NOT NULL,
                    "CreatedAt" text NOT NULL
                );'

        });

        Console::println(
            $result !== false
                ? 'The "migrations" table successfully created.' // TODO: check that the table exists in the first place. This message is confusing if the table already exists.
                : 'The "migrations" table could not be created.',
            $result !== false
                ? '32'
                : '31'
        );
    }

    /**
     * Run the database migrations
     */
    public static function handle(?string $applyVersion) : void
    {
        if ($applyVersion !== null) {

            if (!preg_match('/^\d+$/', $applyVersion)) {
                Console::println('Invalid version number.', '31');
                return;
            }

            if (!$applyVersion = intval($applyVersion)) {
                Console::println('Version number must be 1 or greater.');
                return;
            }
        }

        self::init();

        $currentVersion = DB::prepare(match (DB::$driver) {

            DBDriver::MySQL =>
                'SELECT * FROM `migrations` ORDER BY `Version` DESC',

            DBDriver::PostgreSQL, DBDriver::SQLite =>
                'SELECT * FROM "migrations" ORDER BY "Version" DESC'

        })->single()['Version'] ?? 0;

        if ($applyVersion && $currentVersion >= $applyVersion) {
            Console::println('The specified version or later has already been applied.');
            return;
        }

        $files = self::_getFiles();

        if (!$files) {
            Console::println('No migration files found.');
            return;
        }

        $migrations = [];

        foreach ($files as $file) {

            @list($version, $name) = explode('_', $file, 2);

            if (!$version = intval($version))
                continue;

            if (isset($migrations[$version])) {
                Console::println("Migration aborted! Files of the same migration version found: \"{$migrations[$version]['file']}\" and \"{$file}\".", '31');
                return;
            }

            if ($applyVersion && $version > $applyVersion) {
                Console::println('Migration aborted! The file for the specified migration version was not found.', '31');
                return;
            }

            if ($version <= $currentVersion)
                continue;

            $migrations[$version] = [
                'file' => $file,
                'name' => ucfirst(str_replace('.php', '', $name))
            ];

            if ($version == $applyVersion)
                break;
        }

        Console::println("Migrating from version {$currentVersion} to " . array_key_last($migrations) . ':', '33', 2);

        foreach ($migrations as $version => $migration) {

            Console::println("Migrating to version {$version} ({$migration['name']})...");

            require App::$DIR_MIGRATIONS . "/{$migration['file']}";

            $migrationClass = "\\{$migration['name']}";
            (new $migrationClass)->up();

            $result = DB::prepare(match (DB::$driver) {

                DBDriver::MySQL =>
                    'INSERT INTO `migrations`
                        VALUES (:ver, :name_, :createdAt)',

                DBDriver::PostgreSQL, DBDriver::SQLite =>
                    'INSERT INTO "migrations"
                        VALUES (:ver, :name_, :createdAt)'

            })->bindMultiple([
                ':ver' => $version,
                ':name_' => $migration['name'],
                ':createdAt' => DB::dateTime()
            ])
            ->execute();

            if (!$result) {
                Console::println("Migration stopped! Failed to register version {$version} ({$migration['name']}) in the migrations table.", '31');
                return;
            }

            Console::println("Migration successfully completed.", '32' , 2);
        }
    }

    /**
     * Rollback the last database migration
     */
    public static function rollback(?string $steps) : void
    {
        if ($steps !== null) {

            if (!preg_match('/^\d+$/', $steps)) {
                Console::println('Invalid steps number.', '31');
                return;
            }

            if (!$steps = intval($steps)) {
                Console::println('Steps number must be 1 or greater.');
                return;
            }
        }

        self::init();
        
        $steps = $steps ?? 1;
        $appliedVersions = DB::prepare('SELECT Version FROM migrations ORDER BY Version DESC LIMIT :steps')
            ->bind(':steps', $steps)
            ->resultset();
        $c = count($appliedVersions);

        if ($steps > $c) {
            Console::println("Rollback aborted! The number of steps is greater than the number of applied migrations ({$c}).", '31');
            return;
        }

        $files = self::_getFiles(true);

        if (!$filesCount = count($files)) {
            Console::println('No migration files found.');
            return;
        }

        if ($filesCount < $steps) {
            Console::println("The number of steps is greater than the number of migration files ({$filesCount}).");
            return;
        }

        $migrations = [];
        $step = 1;

        foreach ($files as $file) {
            
            @list($version, $name) = explode('_', $file, 2);

            if (!$version = intval($version))
                continue;

            if (isset($migrations[$version])) {
                Console::println("Rollback aborted! Files of the same migration version found: \"{$migrations[$version]['file']}\" and \"{$file}\".", '31');
                return;
            }

            if ($version > $appliedVersions[0]['Version'])
                continue;

            if ($step > $steps)
                break;

            $migrations[$version] = [
                'file' => $file,
                'name' => ucfirst(str_replace('.php', '', $name))
            ];

            $step++;
        }

        $step = 1;

        Console::println("Rollback {$steps} steps:", '33', 2);

        foreach ($appliedVersions as $version) {

            if ($step > $steps)
                break;

            $version = $version['Version'];

            if (!isset($migrations[$version])) {
                Console::println("Rollback step {$step} aborted! No migration file with version {$version} was found.", '31');
                return;
            }

            Console::println("Step {$step}: rollback the version {$version} ({$migrations[$version]['name']})...");

            require App::$DIR_MIGRATIONS . "/{$migrations[$version]['file']}";

            $migrationClass = "\\{$migrations[$version]['name']}";
            (new $migrationClass)->down();

            $result = DB::prepare('DELETE FROM migrations WHERE Version = :version')
                ->bind(':version', $version)
                ->execute();

            if (!$result || !DB::rowCount()) {
                Console::println("Rollback stopped at step {$step}! Couldn't delete the version {$version} from the migrations table.", '31');
                return;
            }

            Console::println("Rollback successfully completed.", '32', 2);

            $step++;
        }
    }

    /**
     * Returns an array of migration files.
     */
    public static function _getFiles(bool $desc = false) : array
    {
        return array_filter(
            scandir(App::$DIR_MIGRATIONS, $desc ? SCANDIR_SORT_DESCENDING : 0),
            fn($v) => preg_match('/^\d+_[a-zA-Z]+\.php$/', $v)
        );
    }

}
