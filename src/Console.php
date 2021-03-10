<?php

namespace Val;

Abstract Class Console
{
    // TODO: fix tab spacing

    /**
     * Processes the CLI commands.
     */
    public static function open() : void
    {
        App::run();
        self::println();

        $argv = &$_SERVER['argv'];
        $cmd = $argv[1] ?? null;

        if (!$cmd || !preg_match('/^[a-z]+(:[a-z]+)?$/', $cmd)) {
            self::list();
            return;
        }

        @list($command, $method) = explode(':', $cmd);
        $commandClass = ucfirst($command);

        if (!is_file(__DIR__."/Console/{$commandClass}.php")) {
            self::list();
            return;
        }

        $commandClass = "Val\\Console\\{$commandClass}";
        $arg = $argv[2] ?? null;

        if (!$method) {
            $commandClass::handle($arg);
            return;
        }

        if (!method_exists($commandClass, $method)) {
            self::list();
            return;
        }

        $commandClass::$method($arg);
    }

    /**
     * Lists the currently available commands.
     */
    public static function list() : void
    {
        $files = array_filter(
            scandir(__DIR__.'/Console'), 
            fn($v) => preg_match('/^[A-Z][a-z]*\.php$/', $v)
        );

        if (!$files) {
            self::println('No available commands.', '33');
            return;
        }

        self::println('Available commands:', '33');

        $commandsMethods = [];

        foreach ($files as $fileName) {

            $fileName = str_replace('.php', '', $fileName);
            $command = strtolower($fileName);
            $reflector = new \ReflectionClass("Val\\Console\\{$fileName}");

            self::print("  $command\t\t\t", '32');
            self::println(self::getDescription($reflector->getMethod('handle')->getDocComment()));

            $commandsMethods[$command] = [];

            foreach ($reflector->getMethods() as $method) {

                if (!$method->isPublic())
                    continue;

                if ($method->name == 'handle')
                    continue;

                if ($method->name[0] == '_')
                    continue;

                $commandsMethods[$command][] = [
                    'name' => $method->name,
                    'description' => self::getDescription($method->getDocComment())
                ];
            }
        }

        if (!$commandsMethods) {
            
            self::println();
            return;
        }

        foreach ($commandsMethods as $command => $methods) {

            if (!$methods)
                continue;
            
            self::println();
            self::println($command, '33');

            foreach ($methods as $method) {
                self::print("  {$command}:{$method['name']}\t\t", '32');
                self::println($method['description']);
            }
        }
    }

    /**
     * Parses the description from a doc comment.
     */
    protected static function getDescription(string|bool $docComment) : string
    {
        return $docComment ? preg_replace('/\s{2,}/', ' ', substr(str_replace(["\t", PHP_EOL.' *'], '', $docComment), 4, -1)) : '';
    }

    /**
     * Outputs the text.
     */
    public static function print(string $text, ?string $color = null)
    {
        echo $color ? "\033[{$color}m{$text}\033[0m" : $text;
    }

    /**
     * Outputs the text and a new line.
     */
    public static function println(?string $text = null, ?string $color = null, int $count = 1)
    {
        $text && self::print($text, $color);
        for ($i=0; $i<$count; $i++) echo PHP_EOL;
    }

}
