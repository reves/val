<?php

namespace Val\App;

Class Renderer
{
    // View's content
    protected string $content;

    // Registered bindings and their values
    protected array $bindings;

    // Registerd blocks
    protected array $blocks;

    // Minify loaded templates
    protected bool $minify = true;

    /**
     * Registers the $binding's $value.
     */
    public function bind(string $binding, string $value = '') : self
    {
        $this->bindings[$binding] = $value;
        return $this;
    }

    /**
     * Registers multiple bindings using sef::bind method for each of them. The 
     * $relations should represent an array of $binding => $value relations. Read 
     * self::bind method documentation for details.
     */
    public function bindMultiple(array $relations) : self
    {
        foreach ($relations as $binding => $value)
            $this->bind($binding, $value);

        return $this;
    }

    /**
     * Registers the block so makes it visible.
     */
    public function reveal($block) : self
    {
        $this->blocks[] = $block;
        return $this;
    }

    /**
     * Registers multiple $blocks using sef::reveal method for each of them so makes 
     * them visible. Read self::reveal method documentation for details.
     */
    public function revealMultiple(array $blocks) : self
    {
        foreach ($blocks as $block)
            $this->reveal($block);

        return $this;
    }

    /**
     * Sends the content as response.
     */
    public function show() : void
    {
        echo $this->compile()->content;
    }

    /**
     * Returns the content at its current state.
     */
    public function getContent() : string
    {
        return $this->compile()->content;
    }

    /**
     * Loads a template and compiles it.
     */
    public function load(string $name, bool $minify = true) : self
    {
        $this->minify = $minify;
        return $this->reset()->getTemplate($name);
    }

    /**
     * Gets the template file contents.
     */
    protected function getTemplate(string $name, bool $minify = true) : self
    {
        $path = DIR_TEMPLATES . "/{$name}.tpl";

        $this->content = '';

        if (is_file($path)) {

            $this->content = ($minify) ? $this->minify(file_get_contents($path)) : file_get_contents($path);

        }

        return $this;
    }

    /**
     * Minifies the template content.
     */
    protected function minify(string $template) : string
    {   
        // Remove: "\r\n\t" 1+  |   "space" 2+  |   HTML comments
        return ($this->minify) ? preg_replace('/([\r\n\t]+)|([ ]{2,})|(<!--.*?-->)/', '', $template) : $template;
    }

    /**
     * Compiles a raw template.
     */
    protected function compile() : self
    {
        return $this->includeTemplates()->compileBlocks()->compileBindings();
    }

    /**
     * Includes the templates specified in base template.
     */
    protected function includeTemplates() : self
    {
        $matches = [];

        preg_match_all('/\{\@(.*?)\}/', $this->content, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {

            $path = DIR_TEMPLATES . "/{$match[1]}.tpl";

            if (is_file($path)) {

                $template = $this->minify(file_get_contents($path));
                $this->content = preg_replace('/' . preg_quote($match[0], '/') . '/', $template, $this->content);
            
            }
        }

        return $this;
    }

    /**
     * Leaves only registered blocks and remove others.
     */
    protected function compileBlocks() : self
    {
        // Leave Active Blocks
        foreach ($this->blocks as $block ) {

            $this->content = preg_replace("/(\[{$block}\])|(\[\/{$block}\])/i", '', $this->content);

        }

        // Remove Inactive Blocks
        $this->content = preg_replace('/\[(.*?)\].*?\[\/(\1)\]/is', '', $this->content);

        return $this;
    }

    /**
     * Replaces the registered bindings with their values.
     */
    protected function compileBindings() : self
    {
        // Replace Bindings
        foreach ($this->bindings as $binding => $value) {

            $this->content = preg_replace("/\{{$binding}\}/i", $value, $this->content);

        }

        return $this;
    }
    
    /**
     * Resets the renderer.
     */
    public function reset() : self
    {
        $this->content = '';
        $this->bindings = [];
        $this->blocks = [];

        return $this;
    }

}
