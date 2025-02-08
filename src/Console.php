<?php

namespace Val;

use ReflectionClass;

Abstract Class Console
{
    const TAB_SIZE =   2;  // Tab size in spaces
    const ERROR    = '31'; // Red
    const SUCCESS  = '32'; // Green
    const WARNING  = '33'; // Yellow
    const DEBUG    = '35'; // Magenta
    const INFO     = '36'; // Cyan

    /**
     * Processes the CLI commands (CLI entry point).
     */
    public static function open() : void
    {
        App::initDirectories(getcwd());

        $argv = array_values( array_filter(
                $_SERVER['argv'] ?? [],
                fn($arg) => !str_starts_with($arg, '-')
        ));
        $command = ucfirst(strtolower($argv[1] ?? ''));
        $subcommand = strtolower($argv[2] ?? '');
        $argument = $argv[3] ?? null;

        self::println();

        // No command provided, or commands list requested.
        if (!$command || $command === 'Help' || $command === 'List') {
            self::list();
            exit($command ? 0 : 2);
        }

        // Validate command syntax.
        if (!preg_match('/^[a-z]+$/i', $command)) {
            self::println("Invalid \"{$command}\" command syntax. Command must contain only letters.", self::ERROR);
            exit(2);
        }

        // If the subcommand syntax is wrong, treat it as an argument to the 
        // base command.
        if ($subcommand !== '' && !preg_match('/^[a-z]+$/', $subcommand)) {
            $argument = $subcommand;
            $subcommand = '';
        }

        // Check if the command file exists.
        $commandFile = __DIR__."/Console/{$command}.php";
        $commandClass = "Val\\Console\\{$command}";

        if (!is_file($commandFile)) {
            $command = lcfirst($command);
            self::println("Command \"{$command}\" not found.", self::ERROR, 2);
            self::list();
            exit(127);
        }

        // If no subcommand is provided, execute the command.
        if ($subcommand === '') {
            exit((int)!$commandClass::handle($argument));
        }

        // If the subcommand's corresponding method doesn't exist, treat it as 
        // an argument to the base command.
        if (!method_exists($commandClass, $subcommand)) {
            $argument = $subcommand;
            $subcommand = '';
            exit((int)!$commandClass::handle($argument));
        }

        // Execute the subcommand.
        exit((int)!$commandClass::$subcommand($argument));
    }

    /**
     * Lists available commands.
     */
    public static function list() : void
    {
        $fileNames = array_filter(
            scandir(__DIR__.'/Console'),
            fn($name) => preg_match('/^[a-z]+\.php$/i', $name)
        );

        $commands = [];
        $lengths = ['command' => 0, 'subcommand' => 0, 'argument' => 0];

        // Get commands and their arguments.
        foreach ($fileNames as $name) {
            $commandClass = str_replace('.php', '', $name);
            $reflector = new ReflectionClass("Val\\Console\\{$commandClass}");
            $command = strtolower($commandClass);
            $commandDoc = $reflector->getMethod('handle')->getDocComment();
            $lengths['command'] = max($lengths['command'], strlen($command));

            $commands[$command] = [
                'description' => self::parseDescription($commandDoc),
                'argument' => self::parseArgument($commandDoc),
                'subcommands' => [],
            ];

            // Get subcommands and their arguments.
            foreach ($reflector->getMethods() as $method) {
                $name = strtolower($method->name);
                if (!$method->isPublic() || $name[0] == '_' || $name == 'handle') continue;

                $commandDoc = $method->getDocComment();
                $argument = self::parseArgument($commandDoc);
                $lengths['subcommand'] = max($lengths['subcommand'], strlen($name));
                $lengths['argument'] = $argument
                    ? max($lengths['argument'], strlen($argument))
                    : $lengths['argument'];

                $commands[$command]['subcommands'][$name] = [
                    'description' => self::parseDescription($commandDoc),
                    'argument' => $argument,
                ];
            }
        }

        // Print usage, commands and their arguments.
        self::println('Usage:', self::WARNING);
        self::print('val command [subcommand] [');
        self::print('<argument>', '3');
        self::println(']', count: 2);
        self::println('Commands:', self::WARNING);

        foreach ($commands as $command => $data) {
            $argument = $data['argument'];
            $padding = $lengths['command'] - strlen($command)
                + $lengths['subcommand']
                + $lengths['argument'] - ($argument ? strlen($argument) + 3 : 0) 
                + 3 + self::TAB_SIZE;

            self::print($command);
            self::print($argument ? " <{$argument}>" : '', '3');
            self::println(str_repeat(' ', $padding) . $data['description'], self::INFO);

            // Print subcommands and their arguments.
            foreach ($data['subcommands'] as $subcommand => $subData) {
                $argument = $subData['argument'];
                $padding = $lengths['command'] - strlen($command) 
                    + $lengths['subcommand'] - strlen($subcommand) 
                    + $lengths['argument'] - ($argument ? strlen($argument) + 3 : 0) 
                    + 2 + self::TAB_SIZE;

                self::print("{$command} {$subcommand}");
                self::print($argument ? " <{$argument}>" : '', '3');
                self::println(str_repeat(' ', $padding) . $subData['description'], self::INFO);
            }

            if (next($commands)) self::println();
        }
    }

    /**
     * Parses the command description from a doc comment.
     */
    protected static function parseDescription(string|bool $docComment) : string
    {
        if (!$docComment) return '';
        $result = substr($docComment, 3, -1);
        $result = preg_replace('/\*|@.*/', '', $result);
        return trim(preg_replace('/\s+/', ' ', $result));
    }

    /**
     * Parses the argument name from a doc comment.
     */
    protected static function parseArgument(string|bool $docComment) : string
    {
        if (!$docComment) return null;
        $result = substr($docComment, 3);
        $result = str_replace(['*', PHP_EOL], '', $result);
        preg_match('/@param.*?\$(\w+)/i', $result, $matches);
        return $matches[1] ?? '';
    }

    /**
     * Outputs the text with optional formatting.
     */
    public static function print(string $text, ?string $color = null) : void
    {
        if (getenv('NO_COLOR')) $color = false; // https://no-color.org/
        echo $color ? "\033[{$color}m{$text}\033[0m" : $text;
    }

    /**
     * Outputs the text with optional formatting and adds one or more new
     * lines.
     */
    public static function println(?string $text = null, ?string $color = null, int $count = 1) : void
    {
        $text && self::print($text, $color);
        echo str_repeat(PHP_EOL, $count);
    }

    /**
     * Asks the user for confirmation.
     */
    public static function getUserConfirmation() : bool
    {
        $argv = $_SERVER['argv'] ?? [];

        if (in_array('-y', $argv) || in_array('--yes', $argv)) {
            Console::println('Auto answer: y', Console::INFO, 2);
            return true;
        }

        $handle = fopen('php://stdin', 'r');
        $response = trim(fgets($handle));
        fclose($handle);

        return in_array(strtolower($response), ['y', 'yes']);
    }

}
