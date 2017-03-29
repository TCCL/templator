<?php

/**
 * TemplateGenerator.php
 *
 * This file is a part of tccl/templator.
 *
 * @package tccl/templator
 */

namespace TCCL\Templator;

use Exception;

/**
 * Represents a generic template generator that targets an arbitrary template
 * script.
 */
class TemplateGenerator implements Templator {
    /**
     * The path to the script file that represents the base template.
     *
     * @var string
     */
    private $basePage;

    /**
     * An associative array of variables that are exported into the context of
     * the page evaluation.
     *
     * @var array
     */
    private $vars = array();

    /**
     * An associative array mapping component names to template generators.
     *
     * @var array
     */
    private $components = array();

    /**
     * An indexed array of function callbacks that process the page output.
     *
     * @var array
     */
    private $hooks = array();

    /**
     * Cache the evaluation of the template page (since we may invoke it more
     * than once).
     *
     * @var string
     */
    private $cache;

    /**
     * An optional parent Templator object to be used by template scripts.
     *
     * @var Templator
     */
    private $parent;

    /**
     * Determines if the templator is configured to pre-evaluate its content.
     *
     * @var bool
     */
    private $preeval;

    /**
     * An associative array of variables that are exported into every
     * TemplateGenerator instance.
     *
     * @var array
     */
    static private $defaultVars = array();

    /**
     * Constructs a new templator instance
     *
     * @param string $basePage
     *  The file path of the page template file
     * @param bool $preeval
     *  Determines if the templator is configured to pre-evaluate its content.
     */
    public function __construct($basePage,$preeval = true) {
        // Add .php.tpl extension if no extension was specified.
        if (!preg_match('/^[^\.]+\..+/',$basePage)) {
            $basePage .= ".php.tpl";
        }
        $this->basePage = $basePage;
        $this->preeval = $preeval;
    }

    /**
     * Adds a named variable to the list of variables. These variables will be
     * exported into the scope of the template script when it is evaluated.
     *
     * @param string $name
     *  The name for the variable
     * @param mixed $value
     *  The value for the variable
     */
    public function addVariable($name,$value) {
        $this->vars[$name] = $value;
    }

    /**
     * Adds a list of named variables into the list of variables to import into
     * the template script.
     *
     * @param array $vars
     *  An associative array of name/value pairs that represents the variables
     */
    public function addVariables(array $vars) {
        $this->vars += $vars;
    }

    /**
     * Adds a named variable to the list of default variables.
     *
     * @param string $name
     *  The name for the variable
     * @param mixed $value
     *  The value for the variable
     */
    static public function addDefaultVariable($name,$value) {
        self::$defaultVars[$name] = $value;
    }

    /**
     * Adds a list of variables to the list of default variables.
     *
     * @param array $vars
     *  An associative array of name/value pairs that represents the variables
     */
    static public function addDefaultVariables(array $vars) {
        self::$defaultVars += $vars;
    }

    /**
     * Adds a named component to the template page. The component is itself a
     * template generator (i.e. Templator). The template must be completely
     * ready for generation since it is pre-evaluated.
     *
     * @param string    $name
     *  The name for the nested component
     * @param Templator $component
     *  The component object
     */
    public function addComponent($name,Templator $component) {
        // Go ahead and evaluate the component. This is a depth-first evaluation
        // technique that ensures that a component is completely evaluated
        // before the context in which it is used is even considered. This is to
        // place a well-defined ordering on template evaluation which prevents
        // undefined-behavior on any operations that have side-effects.

        $this->components[$name] = $component;
        $component->inherit($this);
        $component->evaluate();
    }

    /**
     * Generate a named component previously specified by a call to
     * addComponent(). The content is written to the output stream. This
     * function should be called within template scripts to inject components.
     *
     * @param  string $name
     *  The name of the component to generate
     */
    public function generateComponent($name) {
        if (!isset($this->components[$name])) {
            throw new Exception(__METHOD__.": component '$name' does not exist");
        }
        $this->components[$name]->generate();
    }

    /**
     * Creates a component directly and then generates it. The component will be
     * an instance of TemplateGenerator.
     *
     * @param string $basePage
     *  The file path for the template file
     * @param bool $preeval
     *  Determines if the templator is configured to pre-evaluate its content.
     */
    public function directComponent($basePage,$preeval = true) {
        $component = new TemplateGenerator($basePage,$preeval);
        $component->inherit($this);
        $component->generate();
    }

    /**
     * Adds a callback function to the list of hooks.
     *
     * @param callable $callback
     *  A PHP callable that takes a single argument that denotes the current output
     *  value
     */
    public function addHook(callable $callback) {
        $this->hooks[] = $callback;
    }

    /**
     * Implements Templator::evaluate()
     */
    public function evaluate() {
        // There is nothing to do if we are configured to write directly to the
        // output stream.
        if (!$this->preeval) {
            return null;
        }

        if (is_null($this->cache)) {
            // Export any variables to make them available to the template page.
            // The $this variable will also be available.
            extract($this->vars);
            extract(self::$defaultVars);

            // Include the target template page so that PHP evaluates it.
            // Capture the output in a PHP output buffer.
            ob_start();
            include $this->basePage;
            $output = ob_get_clean();

            // Pass the output through any processing hooks.
            foreach ($this->hooks as $callback) {
                $output = $callback($output);
            }
            $this->cache = $output;
        }

        return $this->cache;
    }

    /**
     * Implements Templator::generate().
     */
    public function generate() {
        if ($this->preeval) {
            echo $this->evaluate();
        }
        else {
            extract($this->vars);
            extract(self::$defaultVars);
            include $this->basePage;
        }
    }

    /**
     * Implements Templator::inherit().
     */
    public function inherit(Templator $parent) {
        $this->parent = $parent;

        // Inherit variables from parent TemplateGenerator.
        if (is_a($parent,'TemplateGenerator')) {
            $this->addVariables($parent->vars);
        }
    }
}
