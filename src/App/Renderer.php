<?php

namespace Val\App;

use Val\App;

Final Class Renderer
{
    protected static ?self $instance = null;

    // View's content
    protected static string $content = '';

    // Registered bindings and their values
    protected static array $bindings = [];

    // Registerd blocks
    protected static array $blocks = [];

    // Whether to minify loaded templates
    protected static bool $minify;

    // Templates directory
    protected static string $directoryPath = '';

    protected function __construct() {}

    /**
     * Initializes the Renderer module.
     */
    public static function init() : self
    {
        self::$directoryPath = App::$DIR_VIEW;

        return self::$instance ?? self::$instance = new self;
    }

    /**
     * Sets the path to the directory where templates are located.
     */
    public static function from(string $directoryPath) : self
    {
        self::$directoryPath = rtrim($directoryPath, '/');

        return self::$instance;
    }

    /**
     * Loads a template. Throws an Exception if the specified template file is missing.
     * 
     * @throws \RuntimeException
     */
    public static function load(string $file, bool $minify = true) : self
    {
        self::reset();
        self::$minify = $minify;
        $path = self::$directoryPath . "/{$file}";
        $directoryPath = self::$directoryPath;

        if (!is_file($path))
            throw new \RuntimeException("Template file \"{$file}\" is missing in \"{$directoryPath}\" directory.");

        self::$content = self::minify(file_get_contents($path)); // TODO: check file_get_contents for false value

        return self::$instance;
    }

    /**
     * Registers the $binding's $value.
     */
    public static function bind(string $binding, string $value = '') : self
    {
        self::$bindings[$binding] = $value;
        return self::$instance;
    }

    /**
     * Registers multiple bindings using sef::bind method for each of them. The 
     * $relations should represent an array of $binding => $value relations. Read 
     * self::bind method documentation for details.
     */
    public static function bindMultiple(array $relations) : self
    {
        foreach ($relations as $binding => $value)
            self::bind($binding, $value);

        return self::$instance;
    }

    /**
     * Registers the block so makes it visible.
     */
    public static function reveal($block) : self
    {
        self::$blocks[] = $block;
        return self::$instance;
    }

    /**
     * Registers multiple $blocks using sef::reveal method for each of them so makes 
     * them visible. Read self::reveal method documentation for details.
     */
    public static function revealMultiple(array $blocks) : self
    {
        foreach ($blocks as $block)
            self::reveal($block);

        return self::$instance;
    }

    /**
     * Compiles and outputs the content.
     */
    public static function show() : void
    {
        echo self::compile()::$content;
    }

    /**
     * Compiles and returns the content.
     */
    public static function getContent() : string
    {
        return self::compile()::$content;
    }

    /**
     * Minifies the template content.
     */
    protected static function minify(string $template) : string
    {   
        // Remove: "\r\n\t" 1+  |   "space" 2+  |   HTML comments
        return (self::$minify) ? preg_replace('/([\r\n\t]+)|([ ]{2,})|(<!--.*?-->)/', '', $template) : $template;
    }

    /**
     * Compiles a raw template.
     */
    protected static function compile() : self
    {
        return self::includeTemplates()->compileBlocks()->compileBindings();
    }

    /**
     * Includes the templates specified in base template.
     */
    protected static function includeTemplates() : self
    {
        $matches = [];

        preg_match_all('/\{\@(.*?)\}/', self::$content, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {

            $path = self::$directoryPath . "/{$match[1]}";

            if (is_file($path)) {

                $template = self::minify(file_get_contents($path));
                self::$content = preg_replace('/' . preg_quote($match[0], '/') . '/', $template, self::$content);
            
            }
        }

        return self::$instance;
    }

    /**
     * Leaves only registered blocks and remove others.
     */
    protected static function compileBlocks() : self
    {
        // Leave Active Blocks
        foreach (self::$blocks as $block ) {

            self::$content = preg_replace("/(\[{$block}\])|(\[\/{$block}\])/i", '', self::$content);

        }

        // Remove Inactive Blocks
        self::$content = preg_replace('/\[(.*?)\].*?\[\/(\1)\]/is', '', self::$content);

        return self::$instance;
    }

    /**
     * Replaces the registered bindings with their values.
     */
    protected static function compileBindings() : self
    {
        // Replace Bindings
        foreach (self::$bindings as $binding => $value) {

            self::$content = preg_replace("/\{{$binding}\}/i", $value, self::$content);

        }

        return self::$instance;
    }
    
    /**
     * Resets the renderer.
     */
    public static function reset() : self
    {
        self::$content = '';
        self::$bindings = [];
        self::$blocks = [];

        return self::$instance;
    }

}
