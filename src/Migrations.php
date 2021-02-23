<?php

namespace Val;

use Val\App\DB;

Abstract Class Migrations
{
	const TABLE = 'val_migrations';

	protected static function init()
	{
		DB::raw('
			CREATE TABLE IF NOT EXISTS `' . self::TABLE . '` (
			`Version` int unsigned NOT NULL,
			`Name` varchar(255) NOT NULL,
			`CreatedAt` datetime NOT NULL,
			PRIMARY KEY (`Version`)
			) ENGINE=InnoDB;
		');
	}

	// TODO: public static function createAuthTable() {}

	public static function migrate(?int $versionToApply = null)
	{
		if ($versionToApply !== null && $versionToApply < 0) {
			echo "Version number must be 0 or greater.";
			return;
		}

		self::init();

		$files = self::getFiles();

		if (!count($files)) {
			echo "No migration files found.";
			return;
		}

		$currentVersion = DB::prepare('SELECT * FROM '.self::TABLE.' ORDER BY Version DESC')->single()['Version'] ?? -1;

		if ($versionToApply !== null && $versionToApply <= $currentVersion) {
			echo "A later or the same version has already been applied.";
			return;
		}

		$toApply = [];

		foreach ($files as $file) {

			$migration = explode('_', $file, 2);
			$version = intval($migration[0]);

			if (array_key_exists($version, $toApply)) {
				echo "Migration aborted! Files of the same migration version found: \"{$toApply[$version]['file']}\" and \"{$file}\".";
				return;
			}

			if ($version <= $currentVersion)
				continue;

			$toApply[$version] = [
				'file' => $file,
				'name' => ucfirst(str_replace('.php', '', $migration[1]))
			];

			if ($versionToApply !== null && $version == $versionToApply)
				break;
		}

		foreach ($toApply as $version => $migration) {

			echo "Applying version {$version} \"{$migration['name']}\"... ";

			require App::$DIR_MIGRATIONS . "/{$migration['file']}";
			(new $migration['name'])->up();

			$result = DB::prepare('INSERT INTO ' . self::TABLE . ' VALUES(:version, :name, :createdAt)')
				->bind(':version', $version)
				->bind(':name', $migration['name'])
				->bind(':createdAt', DB::dateTime())
				->execute();

			if (!$result) {
				echo "\r\nMigration stopped! Failed to register version {$version} in the migrations table!";
				return;
			}

			echo "Success.\r\n";

		}

	}

	public static function rollback(int $steps = 1)
	{
		if ($steps < 1) {
			echo "Number of steps must be 1 or greater.";
			return;
		}

		$files = self::getFiles(true);
		$filesCount = count($files);

		if (!$filesCount) {
			echo "No migration files found.";
			return;
		}

		if ($filesCount < $steps) {
			echo "The number of steps {$steps} is greater than the number of migration files {$filesCount}.";
			return;
		}

		$appliedVersions = DB::prepare('SELECT Version FROM ' . self::TABLE . ' ORDER BY Version DESC LIMIT :steps')
			->bind(':steps', $steps)
			->resultset();
		$migrationsCount = count($appliedVersions);

		if ($migrationsCount < $steps) {
			echo "The number of steps {$steps} is greater than the number of applied migrations {$migrationsCount}.";
			return;
		}

		$currentVersion = $appliedVersions[0]['Version'];
		$toRollback = [];
		$step = 1;

		foreach ($files as $file) {

			$migration = explode('_', $file, 2);
			$version = intval($migration[0]);

			if (array_key_exists($version, $toRollback)) {
				echo "Rollback aborted! Files of the same migration version found: \"{$toRollback[$version]['file']}\" and \"{$file}\".";
				return;
			}

			if ($version > $currentVersion)
				continue;

			if ($step > $steps)
				break;

			$toRollback[$version] = [
				'file' => $file,
				'name' => ucfirst(str_replace('.php', '', $migration[1]))
			];

			$step++;
		}

		$step = 1;

		foreach ($appliedVersions as $version) {

			if ($step > $steps)
				break;

			$version = $version['Version'];

			if (!array_key_exists($version, $toRollback)) {
				echo "\r\nRollback step {$step} aborted! No migration file with version \"{$version}\" was found.";
				return;
			}

			echo "Step {$step}, rolling back the version {$version} \"{$toRollback[$version]['name']}\"... ";

			require App::$DIR_MIGRATIONS . "/{$toRollback[$version]['file']}";
			(new $toRollback[$version]['name'])->down();

			$result = DB::prepare('DELETE FROM ' . self::TABLE . ' WHERE Version = :version')
				->bind(':version', $version)
				->execute();

			if (!$result || !DB::rowCount()) {
				echo "\r\nRollback stopped! At step {$step}, couldn't delete version {$version} from migrations table.";
				return;
			}

			echo "Success.\r\n";
			$step++;

		}

	}

	protected static function getFiles(bool $desc = false) : array
	{
		return array_filter(
			scandir(App::$DIR_MIGRATIONS, $desc ? SCANDIR_SORT_DESCENDING : null),
			fn($fileName) => preg_match('/^[0-9]+_[a-zA-Z]+\.php$/', $fileName)
		);
	}

	// TODO: Rollback to specific version number

}
