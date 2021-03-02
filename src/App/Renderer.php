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

    // Minify loaded templates
    protected static bool $minify = true;

    protected function __construct() {}

    /**
     * Initializes the Renderer module.
     */
    public static function init() : self
    {
        return self::$instance ?? self::$instance = new self;
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
     * Sends the content as response.
     */
    public static function show() : void
    {
        echo self::compile()::$content;
    }

    /**
     * Returns the content at its current state.
     */
    public static function getContent() : string
    {
        return self::compile()::$content;
    }

    /**
     * Loads a template and compiles it.
     */
    public static function load(string $name, bool $minify = true) : self
    {
        self::$minify = $minify;
        return self::reset()->getTemplate($name);
    }

    /**
     * Gets the template file contents.
     */
    protected static function getTemplate(string $name, bool $minify = true) : self
    {
        $path = App::$DIR_TEMPLATES . "/{$name}.tpl";

        self::$content = '';

        if (is_file($path)) {

            self::$content = ($minify) ? self::minify(file_get_contents($path)) : file_get_contents($path);

        }

        return self::$instance;
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

            $path = App::$DIR_TEMPLATES . "/{$match[1]}.tpl";

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
