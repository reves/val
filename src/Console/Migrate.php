<?php

namespace Val\Console;

use Val\{App, Console};
use Val\App\{Config, DB};

Abstract Class Migrate
{	
	// TODO: Rollback last migration(s) [+bulk]; to a specific version number? (steps currently)
	// TODO: migrate:status
	// TODO: Ask for confirmation before executing

	protected static ?string $dbTable;

	/**
	 * Initializes the database migrations table.
	 */
	protected static function init() : void
	{
		if (Config::db() === null) {
			Console::println('Migration aborted! Database configuration file is missing.');
			App::exit();
		}

		if (!self::$dbTable = Config::db('table_migrations')) {
			Console::println('Migration aborted! Database config field "table_migrations" not specified.');
			App::exit();
		}

		DB::raw('CREATE TABLE IF NOT EXISTS `' . self::$dbTable . '` (
				  `Version` int unsigned NOT NULL,
				  `Name` varchar(255) NOT NULL,
				  `CreatedAt` datetime NOT NULL,
				  PRIMARY KEY (`Version`)
				) ENGINE=InnoDB;
		');
	}

	/**
	 * Run the database migrations
	 */
	public static function handle(?string $applyVersion) : void
	{
		if ($applyVersion !== null) {

			if (!preg_match('/^\d+$/', $applyVersion)) {
				Console::println('Invalid version number.');
				return;
			}

			if (!$applyVersion = intval($applyVersion)) {
				Console::println('Version number must be 1 or greater.');
				return;
			}
		}

		self::init();

		$currentVersion = DB::prepare('SELECT * FROM ' . self::$dbTable . ' ORDER BY Version DESC')
			->single()['Version'] ?? 0;

		if ($applyVersion && $currentVersion >= $applyVersion) {
			Console::println('The specified version or later has already been applied.');
			return;
		}

		$files = self::getFiles();

		if (!$files) {
			Console::println('No migration files found.');
			return;
		}

		$migrations = [];

		foreach ($files as $file) {

			list($version, $name) = explode('_', $file, 2);

			if (!$version = intval($version))
				continue;

			if (isset($migrations[$version])) {
				Console::println("Migration aborted! Files of the same migration version found: \"{$migrations[$version]['file']}\" and \"{$file}\".");
				return;
			}

			if ($applyVersion && $version > $applyVersion) {
				Console::println('Migration aborted! The file for the specified migration version was not found.');
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

			$result = DB::prepare('INSERT INTO ' . self::$dbTable . ' VALUES(:version, :name, :createdAt)')
				->bind(':version', $version)
				->bind(':name', $migration['name'])
				->bind(':createdAt', DB::dateTime())
				->execute();

			if (!$result) {
				Console::println("Migration stopped! Failed to register version {$version} ({$migration['name']}) in the migrations table.");
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
				Console::println('Invalid steps number.');
				return;
			}

			if (!$steps = intval($steps)) {
				Console::println('Steps number must be 1 or greater.');
				return;
			}
		}

		self::init();
		
		$steps = $steps ?? 1;
		$appliedVersions = DB::prepare('SELECT Version FROM ' . self::$dbTable . ' ORDER BY Version DESC LIMIT :steps')
			->bind(':steps', $steps)
			->resultset();
		$c = count($appliedVersions);

		if ($steps > $c) {
			Console::println("Rollback aborted! The number of steps is greater than the number of applied migrations ({$c}).");
			return;
		}

		$files = self::getFiles(true);

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
			
			list($version, $name) = explode('_', $file, 2);

			if (!$version = intval($version))
				continue;

			if (isset($migrations[$version])) {
				Console::println("Rollback aborted! Files of the same migration version found: \"{$migrations[$version]['file']}\" and \"{$file}\".");
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
				Console::println("Rollback step {$step} aborted! No migration file with version {$version} was found.");
				return;
			}

			Console::println("Step {$step}: rollback the version {$version} ({$migrations[$version]['name']})...");

			require App::$DIR_MIGRATIONS . "/{$migrations[$version]['file']}";

			$migrationClass = "\\{$migrations[$version]['name']}";
			(new $migrationClass)->down();

			$result = DB::prepare('DELETE FROM ' . self::$dbTable . ' WHERE Version = :version')
				->bind(':version', $version)
				->execute();

			if (!$result || !DB::rowCount()) {
				Console::println("Rollback stopped at step {$step}! Couldn't delete the version {$version} from the migrations table.");
				return;
			}

			Console::println("Rollback successfully completed.", '32', 2);

			$step++;
		}
	}

	/**
	 * Returns an array of migration files.
	 */
	protected static function getFiles(bool $desc = false) : array
	{
		return array_filter(
			scandir(App::$DIR_MIGRATIONS, $desc ? SCANDIR_SORT_DESCENDING : null),
			fn($v) => preg_match('/^\d+_[a-zA-Z]+\.php$/', $v)
		);
	}

}
