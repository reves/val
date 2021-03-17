<?php

namespace Val\Console;

use Val\{App, Console};
use Val\App\Renderer;

Abstract Class Create
{
    // TODO: preference questions

    const CHMOD_FOLDER	= 0755;
    const CHMOD_FILE	= 0644;

    /**
     * Create base files for the application
     */
    public static function handle()
    {
        if (!self::createDirectory(APP::$DIR_PUBLIC)) {

            return;
        }

        $indexFilePath = App::$DIR_PUBLIC . '/index.php';
        $htaccessFilePath = App::$DIR_PUBLIC . '/.htaccess';

        if (is_file($indexFilePath) && is_file($htaccessFilePath)) {

            Console::println('All the base files for the application already exist.');
            return;
        }

        self::createFile($indexFilePath, 'public/index.php.example');
        self::createFile($htaccessFilePath, 'public/.htaccess.example');
    }

    /**
     * Generate a new application key
     */
    public static function appkey() : bool
    {
        $filePath = APP::$DIR_CONFIG . '/app.php';

        if (!is_file($filePath)) {

            return self::config('app');
        }

        $content = file_get_contents($filePath);
        Console::println('Generating a new application key...');

        if ($content === false) {

            Console::println('Failed to read the app config file!', '31');
            return false;
        }

        $content = preg_replace("/('key'\s*=>\s*')[a-zA-Z0-9\/\+]*(')/", '${1}' . self::_generateAppKey() . '${2}', $content, 1);

        if ($content === null) {

            Console::println('Failed to replace the application key! Unknown preg_replace() error.', '31');
            return false;
        }

        if (file_put_contents($filePath, $content) === false) {

            Console::println('Failed to write to the app config file!', '31');
            return false;
        }

        Console::println('A new application key successfully generated and replaced.', '32');
        return true;
    }

    /**
     * Create a new config file
     */
    public static function config(?string $name) : bool
    {
        if ($name === null) {

            Console::println('No config name specified.');
            return false;
        }

        if (!preg_match('/^[a-z]+$/', $name = strtolower($name))) {

            Console::println('Invalid config name.', '31');
            return false;
        }

        if (!self::createDirectory(APP::$DIR_CONFIG)) {

            return false;
        }

        $filePath = APP::$DIR_CONFIG . "/{$name}.php";

        if (is_file($filePath)) {

            Console::println("Failed to create a new config file! The config file \"{$name}.php\" already exists.", '31');
            return false;
        }

        if ($name == 'auth' && (!is_file(APP::$DIR_CONFIG . '/db.php') || !is_file(APP::$DIR_CONFIG . '/app.php'))) {

            Console::println('Failed to create a new config file! Both the "db" and "app" configs are required before "auth" is created.', '31'); // TODO: create these files automatically
            return false;
        }

        $template = match ($name) { // TODO: scandir for matching examples
            'app'	=> 'app.php.example',
            'auth'	=> 'auth.php.example',
            'db'	=> 'db.php.example',
            'res'	=> 'res.php.example',
            default	=> 'config.php.example'
        };

        $bindings = ($name == 'app') ? ['key' => self::_generateAppKey()] : [];

        if (!self::createFile($filePath, "config/{$template}", $bindings)) {

            return false;
        }

        if ($name == 'auth') {
            
            Console::println();
            self::migration('CreateAuthSessionsTable');
        }

        return true;
    }

    /**
     * Create a new API endpoint class
     */
    public static function api(?string $className) : bool
    {
        if ($className === null) {
            Console::println('No API endpoint class name specified.');
            return false;
        }

        if (!preg_match('/^[A-Z][a-z]*$/', $className = ucfirst(strtolower($className)))) {
            Console::println('Invalid API endpoint class name.', '31');
            return false;
        }

        if (!self::createDirectory(APP::$DIR_API)) {

            return false;
        }

        $filePath = APP::$DIR_API . "/{$className}.php";

        if (is_file($filePath)) {

            Console::println("Failed to create a new API endpoint class! The API endpoint class \"{$className}.php\" already exists.", '31');
            return false;
        }

        return self::createFile($filePath, 'api/Endpoint.php.example', ['name' => $className]);
    }

    /**
     * Create a new migration class
     */
    public static function migration(?string $className) : bool
    {
        if ($className === null) {
            Console::println('No migration class name specified.');
            return false;
        }

        if (!preg_match('/^[a-zA-Z]+$/', $className = ucfirst($className))) {
            Console::println('Invalid migration class name.', '31');
            return false;
        }

        if (!self::createDirectory(APP::$DIR_MIGRATIONS)) {

            return false;
        }

        $files = Migrate::_getFiles(true);
        echo $files[0];
        $newVersion = $files ? intval(strtok($files[0], '_')) + 1 : 1;
        $filePath = APP::$DIR_MIGRATIONS . "/{$newVersion}_{$className}.php";

        $template = match ($className) { // TODO: scandir for matching examples
            'CreateAuthSessionsTable'	=> 'CreateAuthSessionsTable.php.example',
            default						=> 'Migration.php.example'
        };

        return self::createFile($filePath, "migrations/{$template}", ['name' => $className]);
    }

    /**
     * Creates the specified directory. Attention: returns true even if the folder already 
     * exists.
     */
    protected static function createDirectory(string $path) : bool
    {
        if (!file_exists($path)) {

            $name = basename($path);

            Console::println("Creating the {$name} directory...");

            if (!is_writable(App::$DIR_ROOT)) {

                Console::println("Failed to create the {$name} directory! The root directory is not writable.", '31');
                return false;
            }

            if (!mkdir($path, self::CHMOD_FOLDER)) {
                
                Console::println("Failed to create the {$name} directory!", '31');
                return false;
            }

            Console::println("The {$name} directory successfully created.", '32', 2);

        }

        return true;
    }

    /**
     * Creates the specified file. Attention: returns true even if the file already exists.
     */
    protected static function createFile(string $path, string $template, array $bindings = []) : bool
    {
        if (!file_exists($path)) {

            $name = basename($path);
            $directory = dirname($path);
            $directoryName = basename($directory);

            Console::println("Creating the {$name} file...");

            if (!is_writable($directory)) {

                Console::println("Failed to create the {$name} file! The {$directoryName} directory is not writable.", '31');
                return false;
            }

            $result = file_put_contents(
                $path,
                Renderer::from(__DIR__ . '/create/' . dirname($template))
                    ->load(basename($template), false)
                    ->bindMultiple($bindings)
                    ->getContent()
            );

            if ($result === false) {

                Console::println("Failed to create the {$name} file!", '31');
                return false;
            }

            chmod($path, self::CHMOD_FILE);
            Console::println("The {$name} file successfully created in the {$directoryName} directory.", '32', 2);

        }

        return true;
    }

    /**
     * Generates an application secret key.
     */
    public static function _generateAppKey() : string
    {
        return \sodium_bin2base64(\sodium_crypto_secretbox_keygen(), \SODIUM_BASE64_VARIANT_ORIGINAL_NO_PADDING);
    }

}
