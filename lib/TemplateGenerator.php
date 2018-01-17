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
 * TemplateGenerator
 *
 * Represents a generic template generator that targets an arbitrary template
 * script. This templator provides variables, HTML entity escaping, evaluation
 * caching, output hooks and nested components.
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
     * A list of variable names that should not be escaped when output.
     *
     * @var array
     */
    private $noescape = array();

    /**
     * Flag whether we've escaped our local template variables or not.
     *
     * @var bool
     */
    private $escaped = false;

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
     * A list of default variables names that should not be escaped.
     *
     * @var array
     */
    static private $defaultNoescape = array();

    /**
     * Flag whether global default variables have been escaped or not.
     *
     * @var bool
     */
    static private $defaultEscaped = false;

    /**
     * Constructs a new templator instance
     *
     * @param string $basePage
     *  The file path of the page template file
     * @param bool $preeval
     *  Determines if the templator is configured to pre-evaluate its content.
     */
    public function __construct($basePage,$preeval = false) {
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
     * @param bool $escape
     *  If true, then the named variable will be escaped.
     */
    public function addVariable($name,$value,$escape = true) {
        $this->vars[$name] = $value;

        if (!$escape) {
            $this->noescape[$name] = true;
        }
    }

    /**
     * Adds a list of named variables into the list of variables to import into
     * the template script.
     *
     * @param array $vars
     *  An associative array of name/value pairs that represents the variables
     * @param bool $escape
     *  If true, then the named variables will be escaped.
     */
    public function addVariables(array $vars,$escape = true) {
        $this->vars += $vars;

        if (!$escape) {
            $this->noescape += array_fill_keys(array_keys($vars),true);
        }
    }

    /**
     * Adds a named variable to the list of default variables.
     *
     * @param string $name
     *  The name for the variable
     * @param mixed $value
     *  The value for the variable
     * @param bool $escape
     *  If true, then the named variables will be escaped.
     */
    static public function addDefaultVariable($name,$value,$escape = true) {
        self::$defaultVars[$name] = $value;

        if (!$escape) {
            self::$defaultNoescape[$name] = true;
        }
    }

    /**
     * Adds a list of variables to the list of default variables.
     *
     * @param array $vars
     *  An associative array of name/value pairs that represents the variables
     */
    static public function addDefaultVariables(array $vars,$escape = true) {
        self::$defaultVars += $vars;

        if (!$escape) {
            self::$defaultNoescape += array_fill_keys(array_keys($vars),true);
        }
    }

    /**
     * Adds a named component to the template page. The component is itself a
     * template generator (i.e. Templator). The template must be completely
     * ready for generation since it is pre-evaluated.
     *
     * @param string $name
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
        if ($this->preeval) {
            $component->evaluate();
        }
    }

    /**
     * Generate a named component previously specified by a call to
     * addComponent(). The content is written to the output stream. This
     * function should be called within template scripts to inject components.
     *
     * @param string $name
     *  The name of the component to generate.
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
    public function directComponent($basePage,$preeval = false) {
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
     * Resets the templator back to its initial state with no variables,
     * components or hooks. The evaluation cache is also cleared.
     */
    public function reset() {
        $this->hooks = [];
        $this->components = [];
        $this->resetVariables();
        $this->clearCache();
    }

    /**
     * Resets the state of the templator's variable list.
     */
    public function resetVariables() {
        $this->vars = [];
        $this->noescape = [];
        $this->escaped = false;
    }

    /**
     * Resets the state of the default variable list.
     */
    public static function resetDefaultVariables() {
        self::$defaultVariables = [];
        self::$defaultNoescape = [];
        self::$defaultEscaped = false;
    }

    /**
     * Clears the template generator's evaluation cache, allowing for another
     * full evaluation.
     */
    public function clearCache() {
        unset($this->cache);
    }

    /**
     * Implements Templator::evaluate()
     */
    public function evaluate() {
        // Cache the evaluation in case the template is instantiated multiple
        // times.
        if (!isset($this->cache)) {
            // Set up output buffer.
            ob_start();

            // Generate output and capture to variable.
            $this->outputPage();
            $output = ob_get_clean();

            if (!empty($output)) {
                // Pass the output through any processing hooks.
                foreach ($this->hooks as $callback) {
                    $output = $callback($output);
                }
                $this->cache = $output;
            }
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
            $this->outputPage();
        }
    }

    /**
     * Implements Templator::inherit().
     */
    public function inherit(Templator $parent) {
        $this->parent = $parent;
    }

    private static function escape_recursive(array &$bucket,array $noescape,$escape = null) {
        foreach ($bucket as $name => &$value) {
            // Only escape variables that were not excluded or whose parent
            // variable was not excluded. In this way any variable that is
            // excluded has its children excluded as well.

            $doescape = isset($escape) ? $escape : !isset($noescape[$name]);

            if (is_string($value) && $doescape) {
                $value = htmlentities($value);
            }
            else if (is_array($value)) {
                self::escape_recursive($value,$noescape,$doescape);
            }
        }
    }

    private function getEscapedVariables() {
        // Make sure variable lists have been escaped. This involves running
        // every string variable through htmlentities() recursively except those
        // marked 'noescape'.

        if (!$this->escaped) {
            self::escape_recursive($this->vars,$this->noescape);
            $this->escaped = true;
        }
        if (!self::$defaultEscaped) {
            self::escape_recursive(self::$defaultVars,self::$defaultNoescape);
            self::$defaultEscaped = true;
        }

        // Make list of variables to extract using local, default and parent
        // variable lists.

        $vars = $this->vars + self::$defaultVars;
        if (is_a($this->parent,'\TCCL\Templator\TemplateGenerator')) {
            $vars += $this->parent->getEscapedVariables();
        }

        return $vars;
    }

    private function outputPage() {
        // Extract variables into the scope of this method call. Then include
        // the template source code to generate output.

        extract($this->getEscapedVariables());
        include $this->basePage;
    }
}
