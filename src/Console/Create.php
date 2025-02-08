<?php

namespace Val\Console;

use Val\{App, Console};
use Val\App\Renderer;

Abstract Class Create
{
    const DIR_CREATE = __DIR__.'/create';
    const CHMOD_FOLDER	= 0755;
    const CHMOD_FILE	= 0644;

    /**
     * Creates all the necessary files for the app.
     */
    public static function handle() : bool
    {
        Console::println('Creating the main application files:', count: 2);

        // Create the public directory and its files.
        if (self::createDirectory(APP::$DIR_PUBLIC)) {

            self::createFile(
                App::$DIR_PUBLIC.'/index.php',
                self::DIR_CREATE.'/public/index.example.php'
            );

            self::createFile(
                App::$DIR_PUBLIC.'/.htaccess',
                self::DIR_CREATE.'/public/example.htaccess'
            );
        }

        // Create the config files.
        self::config('env', ['key' => self::_generateAppKey()]);
        self::config('envdev', ['key' => self::_generateAppKey()]);
        self::config('app');
        self::config('db');
        self::config('auth');

        // Create the migration file for the sessions table.
        glob(APP::$DIR_MIGRATIONS.'/*_CreateSessionsTable.php')
            ? Console::println('The "CreateSessionsTable" migration file already exists, skipping...', Console::DEBUG, 2)
            : self::migration('CreateSessionsTable');

        // Create the .gitignore file.
        self::createFile(
            APP::$DIR_ROOT.'/.gitignore',
            self::DIR_CREATE.'/example.gitignore'
        );

        return true;
    }

    /**
     * Creates a config.
     * 
     * @param $name The name of the config file to create.
     */
    public static function config(?string $name = null, ?array $bindings = null) : bool
    {
        $name ??= 'config';

        // Validate the config file name.
        if (!preg_match('/^[a-z]+$/', $name = strtolower($name))) {
            Console::println("Invalid config file name \"{$name}\": must contain only letters.", Console::ERROR);
            return false;
        }

        if ($name === 'envdev') $name = 'env.dev'; // special case

        // Create the config directory.
        if (!self::createDirectory(APP::$DIR_CONFIG))
            return false;

        // Create the config file.
        $path = APP::$DIR_CONFIG."/{$name}.php";

        if (is_file($path)) {
            Console::println("The \"{$name}.php\" config file already exists, skipping...", Console::DEBUG, 2);
            return true;
        }

        $templatesPath = self::DIR_CREATE.'/config';
        $templatePath = $templatesPath . "/{$name}.example.php";
        $templatePath = is_file($templatePath)
            ? $templatePath
            : "{$templatesPath}/config.example.php";

        return self::createFile($path, $templatePath, $bindings);
    }

    /**
     * Creates an API endpoint.
     * 
     * @param $name The name of the API endpoint to create.
     */
    public static function api(?string $name = null) : bool
    {
        $name ??= 'test';

        // Validate the API class file name.
        if (!preg_match('/^[a-z]+$/i', $name = ucfirst(strtolower($name)))) {
            Console::println("Invalid API class file name \"{$name}\": must contain only letters.", Console::ERROR);
            return false;
        }

        // Create the api directory.
        if (!self::createDirectory(APP::$DIR_API))
            return false;

        // Create the API endpoint class file.
        $path = APP::$DIR_API."/{$name}.php";

        if (is_file($path)) {
            Console::println("The \"{$name}.php\" API endpoint class file already exists, skipping...", Console::DEBUG, 2);
            return true;
        }

        $templatePath = self::DIR_CREATE.'/api/Endpoint.example.php';
        return self::createFile($path, $templatePath, ['name' => $name]);
    }

    /**
     * Creates a migration.
     * 
     * @param $name The name of the migration to create.
     */
    public static function migration(?string $name = null) : bool
    {
        $name ??= 'NewMigration';

        // Validate the migration class name.
        if (!preg_match('/^[a-z]+$/i', $name = ucfirst($name))) {
            Console::println("Invalid migration class name \"{$name}\": must contain only letters.", Console::ERROR);
            return false;
        }

        // Create the migrations directory.
        if (!self::createDirectory(APP::$DIR_MIGRATIONS))
            return false;

        // Calculate the version of the new migration.
        $migrations = Migrate::_getMigrations(true);
        $version = $migrations ? array_key_first($migrations) + 1 : 1;

        // Create the migration file.
        $path = APP::$DIR_MIGRATIONS."/{$version}_{$name}.php";
        $templatesPath = self::DIR_CREATE.'/migrations';
        $templatePath = $templatesPath . "/{$name}.example.php";
        $templatePath = is_file($templatePath)
            ? $templatePath
            : "{$templatesPath}/Migration.example.php";

        return self::createFile($path, $templatePath, ['name' => $name]);
    }

    /**
     * Regenerates the app key in "config/env.dev.php".
     */
    public static function appkey() : bool
    {
        $path = APP::$DIR_CONFIG.'/env.dev.php';

        // Create the "env.dev.php" config if it does not exist.
        // It will also generate the app key.
        if (!is_file($path))
            return self::config('envdev', ['key' => self::_generateAppKey()]);

        // Read the config file.
        $content = file_get_contents($path);

        if ($content === false) {
            Console::println('Failed to read contents of the "env.dev.php" config file!', Console::ERROR);
            return false;
        }

        // Regenerate the app key.
        Console::println('Regenerating the app key in "config/env.dev.php":');

        $content = preg_replace("/((['\"])key\\2\s*=>\s*(['\"])).*?(\\3)/",
            '${1}'.self::_generateAppKey().'${4}', $content, 1);

        if ($content === null) {
            Console::println('Failed to regenerate the application key: preg_replace() error.', Console::ERROR);
            return false;
        }

        if (file_put_contents($path, $content) === false) {
            Console::println('Failed to write to the "env.dev.php" config file!', Console::ERROR);
            return false;
        }

        Console::println('The app key has been regenerated successfully.', Console::SUCCESS);
        return true;
    }

    /**
     * Creates the specified directory if it does not exist. Returns true on
     * success, or if the directory already exists.
     */
    protected static function createDirectory(string $path) : bool
    {
        $name = basename($path);

        if (is_dir($path)) {
            Console::println("The \"{$name}\" directory already exists, skipping...", Console::DEBUG, 2);
            return true;
        }

        Console::println("Creating the \"{$name}\" directory:");

        if (!is_writable(App::$DIR_ROOT)) {
            Console::println("Failed to create the \"{$name}\" directory: root directory is not writable!", Console::ERROR, 2);
            return false;
        }

        if (!mkdir($path, self::CHMOD_FOLDER)) {
            Console::println("Failed to create the \"{$name}\" directory!", Console::ERROR, 2);
            return false;
        }

        Console::println("Directory \"{$name}\" successfully created.", Console::SUCCESS, 2);
        return true;
    }

    /**
     * Creates the specified file from the template if it does not exist.
     * Returns true on success, or if the file already exists.
     */
    protected static function createFile(string $path, string $templatePath, ?array $bindings = null) : bool
    {
        $name = basename($path);

        if (is_file($path)) {
            Console::println("The \"{$name}\" file already exists, skipping...", Console::DEBUG, 2);
            return true;
        }

        Console::println("Creating the \"{$name}\" file:");

        $directoryPath = dirname($path);
        $directoryName = basename($directoryPath);

        if (!is_writable($directoryPath)) {
            Console::println("Failed to create the \"{$name}\" file: the \"{$directoryName}\" directory is not writable!", Console::ERROR, 2);
            return false;
        }

        Renderer::init();
        $content = Renderer::setPath(dirname($templatePath))
            ->load(basename($templatePath), false)
            ->bindMultiple($bindings ?? [])
            ->getContent();
        $result = file_put_contents($path, $content);

        if ($result === false) {
            Console::println("Failed to create the \"{$name}\" file!", Console::ERROR, 2);
            return false;
        }

        if (!chmod($path, self::CHMOD_FILE)) {
            Console::println("Failed to set the \"{$name}\" file permissions!", Console::ERROR, 2);
            return false;
        }

        Console::println("File \"{$directoryName}/{$name}\" successfully created.", Console::SUCCESS, 2);
        return true;
    }

    /**
     * Generates an application secret key.
     */
    public static function _generateAppKey() : string
    {
        return \sodium_bin2base64(
            \sodium_crypto_secretbox_keygen(),
            \SODIUM_BASE64_VARIANT_ORIGINAL_NO_PADDING
        );
    }

}
