<?php

namespace Val\Console;

use Val\{App, Console};
use Val\App\Renderer;

Abstract Class Create
{
	// TODO: preference questions
	// TODO: if config == "auth" ---> create also a migration w/ db table for the sessions

	const CHMOD_FOLDER	= 0755;
	const CHMOD_FILE	= 0644;

	/**
	 * Create base files for the application
	 */
	public static function handle()
	{
		Console::println('Creating public directory...');

		if (!file_exists(APP::$DIR_PUBLIC)) {

			if (is_writable(App::$DIR_ROOT)) {

				if (mkdir(App::$DIR_PUBLIC, self::CHMOD_FOLDER)) {

					Console::println('Public directory successfully created.', '32', 2);

				} else {

					Console::println('Failed to create public directory!');
					return;
				}

			} else {

				Console::println('Failed to create public directory! The root directory is not writable.');
				return;
			}

		} else {

			Console::println('Public directory already exists, skipping.', count:2);

		}

		Console::println('Creating the index.php file...');
		$indexFilePath = APP::$DIR_PUBLIC . '/index.php';

		if (!file_exists($indexFilePath)) {

			if (is_writable(App::$DIR_PUBLIC)) {

				$result = file_put_contents(
					$indexFilePath,
					Renderer::load('index.php.example', false, __DIR__.'/create/public')->getContent()
				);

				if ($result !== false) {

					chmod($indexFilePath, self::CHMOD_FILE);
					Console::println('The index.php file successfully created.', '32', 2);

				} else {

					Console::println('Failed to create the index.php file!');
					return;
				}

			} else {

				Console::println('Failed to create the index.php file! Public directory is not writable.');
				return;
			}

		} else {

			Console::println('The index.php file already exists, skipping.', count:2);

		}
		
		Console::println('Creating the .htaccess file...');
		$htaccessFilePath = APP::$DIR_PUBLIC . '/.htaccess';

		if (!file_exists($htaccessFilePath)) {

			if (is_writable(App::$DIR_PUBLIC)) {

				$result = file_put_contents(
					$htaccessFilePath,
					Renderer::load('.htaccess.example', false, __DIR__.'/create/public')->getContent()
				);

				if ($result !== false) {

					chmod($htaccessFilePath, self::CHMOD_FILE);
					Console::println('The .htaccess file successfully created.', '32');

				} else {

					Console::println('Failed to create the .htaccess file!');
					return;
				}

			} else {

				Console::println('Failed to create the .htaccess file! Public directory is not writable.');
				return;
			}

		} else {

			Console::println('The .htaccess file already exists, skipping.');

		}
	}

	/**
	 * Generate a new application key
	 */
	public static function appkey()
	{
		if (!file_exists(APP::$DIR_CONFIG)) {

			Console::println('Creating config directory...');

			if (is_writable(App::$DIR_ROOT)) {

				if (mkdir(App::$DIR_CONFIG, Create::CHMOD_FOLDER)) {

					Console::println('Config directory successfully created.', '32', 2);

				} else {

					Console::println('Failed to create config directory!');
					return;
				}

			} else {

				Console::println('Failed to create config directory! The root directory is not writable.');
				return;
			}
		}

		$filePath = APP::$DIR_CONFIG . '/app.php';

		if (!is_file($filePath)) {

			Console::println('Creating app config file...');

			if (is_writable(App::$DIR_CONFIG)) {

				$result = file_put_contents(
					$filePath, 
					Renderer::load('app.php.example', false, __DIR__.'/create/config')
					->bind('key', self::_generateAppKey())
					->getContent()
				);

				if ($result !== false) {

					chmod($filePath, Create::CHMOD_FILE);
					Console::println("App config file \"app.php\" with the generated app key successfully created.", '32');
					return;

				} else {

					Console::println('Failed to create app config file!');
					return;
				}

			} else {

				Console::println('Failed to create app config file! Config directory is not writable.');
				return;
			}
		}

		$config = file_get_contents($filePath);

		if ($config === false) {

			Console::println('Failed to read the app config file!');
			return;
		}

		$config = preg_replace("/('key'\s*=>\s*')[a-zA-Z0-9\/\+]*(')/", '${1}' . self::_generateAppKey() . '${2}', $config, 1);

		if ($config === null) {

			Console::println('Failed to replace the application key! Unknown preg_replace() function error.');
			return;
		}

		$result = file_put_contents($filePath, $config);

		if ($result === false) {

			Console::println('Failed to write to the app config file!');
			return;
		}

		Console::println('A new application key successfully generated.', '32');
	}

	/**
	 * Create a new config file
	 */
	public static function config(?string $name)
	{
		if ($name === null) {
			Console::println('No config name specified.');
			return;
		}

		if (!preg_match('/^[a-z]+$/', $name = strtolower($name))) {
			Console::println('Invalid config name.');
			return;
		}

		if (!file_exists(APP::$DIR_CONFIG)) {

			Console::println('Creating config directory...');

			if (is_writable(App::$DIR_ROOT)) {

				if (mkdir(App::$DIR_CONFIG, Create::CHMOD_FOLDER)) {

					Console::println('Config directory successfully created.', '32', 2);

				} else {

					Console::println('Failed to create config directory!');
					return;
				}

			} else {

				Console::println('Failed to create config directory! The root directory is not writable.');
				return;
			}
		}

		Console::println('Creating a new config file...');
		$filePath = APP::$DIR_CONFIG . "/{$name}.php";

		if (is_writable(App::$DIR_CONFIG)) {

			$file = match ($name) {
				'app'	=> 'app.php.example',
				'auth'	=> 'auth.php.example',
				'db'	=> 'db.php.example',
				default	=> 'config.php.example'
			};

			$template = Renderer::load($file, false, __DIR__.'/create/config');

			if ($name == 'app')
				$template->bind('key', self::_generateAppKey());

			$result = file_put_contents($filePath, $template->getContent());

			if ($result !== false) {

				chmod($filePath, Create::CHMOD_FILE);
				Console::println("A new config file \"{$name}.php\" successfully created.", '32');

			} else {

				Console::println('Failed to create a new config file!');
				return;
			}

		} else {

			Console::println('Failed to create a new config file! Config directory is not writable.');
			return;
		}
	}

	/**
	 * Create a new API endpoint class
	 */
	public static function api(?string $className)
	{
		if ($className === null) {
			Console::println('No API endpoint class name specified.');
			return;
		}

		if (!preg_match('/^[A-Z][a-z]*$/', $className = ucfirst(strtolower($className)))) {
			Console::println('Invalid API endpoint class name.');
			return;
		}

		if (!file_exists(APP::$DIR_API)) {

			Console::println('Creating api directory...');

			if (is_writable(App::$DIR_ROOT)) {

				if (mkdir(App::$DIR_API, Create::CHMOD_FOLDER)) {

					Console::println('Api directory successfully created.', '32', 2);

				} else {

					Console::println('Failed to create api directory!');
					return;
				}

			} else {

				Console::println('Failed to create api directory! The root directory is not writable.');
				return;
			}
		}

		Console::println('Creating a new API endpoint class...');
		$filePath = APP::$DIR_API . "/{$className}.php";

		if (is_writable(App::$DIR_API)) {

			$result = file_put_contents(
				$filePath,
				Renderer::load('Endpoint.php.example', false, __DIR__.'/create/api')
					->bind('name', $className)
					->getContent()
			);

			if ($result !== false) {

				chmod($filePath, Create::CHMOD_FILE);
				Console::println("A new API endpoint class \"{$className}.php\" successfully created.", '32');

			} else {

				Console::println('Failed to create a new API endpoint class!');
				return;
			}

		} else {

			Console::println('Failed to create a new API endpoint class! Api directory is not writable.');
			return;
		}
	}

	/**
	 * Create a new migration class
	 */
	public static function migration(?string $className)
	{
		if ($className === null) {
			Console::println('No migration class name specified.');
			return;
		}

		if (!preg_match('/^[a-zA-Z]+$/', $className = ucfirst($className))) {
			Console::println('Invalid migration class name.');
			return;
		}

		if (!file_exists(APP::$DIR_MIGRATIONS)) {

			Console::println('Creating migrations directory...');

			if (is_writable(App::$DIR_ROOT)) {

				if (mkdir(App::$DIR_MIGRATIONS, Create::CHMOD_FOLDER)) {

					Console::println('Migrations directory successfully created.', '32', 2);

				} else {

					Console::println('Failed to create migrations directory!');
					return;
				}

			} else {

				Console::println('Failed to create migrations directory! The root directory is not writable.');
				return;
			}
		}

		Console::println('Creating a new migration class...');

		$files = Migrate::_getFiles(true);
		$newVersion = $files ? intval(explode('_', $files[0], 2)[0]) + 1 : 1;		
		$filePath = APP::$DIR_MIGRATIONS . "/{$newVersion}_{$className}.php";

		if (is_writable(App::$DIR_MIGRATIONS)) {

			$result = file_put_contents(
				$filePath,
				Renderer::load('Migration.php.example', false, __DIR__.'/create/migrations')
					->bind('name', $className)
					->getContent()
			);

			if ($result !== false) {

				chmod($filePath, Create::CHMOD_FILE);
				Console::println("A new migration class \"{$newVersion}_{$className}.php\" successfully created.", '32');

			} else {

				Console::println('Failed to create a new migration class!');
				return;
			}

		} else {

			Console::println('Failed to create a new migration class! Migrations directory is not writable.');
			return;
		}
	}

	/**
	 * Generates an application secret key.
	 */
	public static function _generateAppKey() : string
	{
		return \sodium_bin2base64(\sodium_crypto_secretbox_keygen(), \SODIUM_BASE64_VARIANT_ORIGINAL_NO_PADDING);
	}

}
