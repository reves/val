<?php

namespace Val\Console;

use Val\{App, Console};
use Val\App\Renderer;

Abstract Class Create
{
	const CHMOD_FOLDER	= 0755;
	const CHMOD_FILE	= 0644;

	public static function handle() {

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
					Renderer::load('index.php.example', false, __DIR__.'/create')->getContent()
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
					Renderer::load('.htaccess.example', false, __DIR__.'/create')->getContent()
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

	// TODO: key generation ---> sodium_bin2base64(sodium_crypto_secretbox_keygen(), SODIUM_BASE64_VARIANT_ORIGINAL_NO_PADDING);

}
