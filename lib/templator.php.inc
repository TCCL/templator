<?php

/**
 * templator.php.inc
 *
 * This library provides a simple HTML template generator.
 *
 * @package templator
 */

/**
 * The base interface that describes a template generator
 */
interface Templator {
    /**
     * Evaluates the content of the template page and returns it as a string
     *
     * @return string
     *
     */
    public function evaluate();

    /**
     * Writes the evaluate content to the output stream (this does what evaluate()
     * does except it echos it to the output stream instead of returning it)
     */
    public function generate();
}

/**
 * Represents a generic template page generator.
 */
class TemplateGenerator implements Templator {
    /**
     * The path to the template file that represents the base page
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
     * An associative array of variables that are exported into every TemplateGenerator
     * instance.
     *
     * @var array
     */
    static private $defaultVars = array();

    /**
     * Constructs a new templator instance
     *
     * @param string $basePage
     *  The file path of the page template file
     */
    public function __construct($basePage) {
        // Add .php.tpl extension if no extension was specified.
        if (!preg_match('/^[^\.]+\..+/',$basePage)) {
            $basePage .= ".php.tpl";
        }
        $this->basePage = $basePage;
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
     * Adds a list of named variables into the list of varibles. These variables
     * will be added
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
     * template generator (i.e. Templator). The template must be completely ready
     * for generation since it is pre-evaluated.
     *
     * @param string    $name
     *  The name for the nested component
     * @param Templator $component
     *  The component object
     */
    public function addComponent($name,Templator $component) {
        // Go ahead and evaluate the component. This is a depth-first evaluation
        // technique that ensures we that a component is completely evaluated
        // before the context in which it is used is even considered. This is to
        // place a well-defined ordering on the order of evaluation to prevent
        // undefined-behavior on any operations that have side-effects.
        $this->components[$name] = $component;
        $component->evaluate();
    }

    /**
     * Generate a named component previously specified by a call to
     * addComponent(). The content is written to the output stream.
     *
     * @param  string $name
     *  The name of the component to generate
     */
    public function generateComponent($name) {
        if (!isset($this->components[$name])) {
            throw new Exception(__METHOD__.": component '$name' does not exist");
        }
        echo $this->components[$name]->evaluate();
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
        if (is_null($this->cache)) {
            // Export any variables to make them available to the template page.
            extract($this->vars);
            extract(self::$defaultVars);

            // Include the target template page so that PHP evaluates it. Capture
            // the output in a PHP output buffer.
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
     * Implements Templator::generate()
     */
    public function generate() {
        error_log("g");
        echo $this->evaluate();
    }
}

/**
 * A specific template generator for generating a top-level HTML page.
 */
class PageGenerator extends TemplateGenerator {
    /**
     * An array of CSS stylesheets to add to the page.
     *
     * @var array
     */
    private $css = array();

    /**
     * An array of JavaScript files to add to the page.
     * @var array
     */
    private $js = array();

    /**
     * Add a stylesheet to the page.
     *
     * @param string $filePath
     *  The path to the CSS file
     */
    public function addStylesheet($filePath) {
        $this->css[] = $filePath;
    }

    /**
     * Add a JavaScript file to the page.
     *
     * @param string $filePath
     *  The path to the JavaScript file
     */
    public function addJavaScript($filePath) {
        $this->js[] = $filePath;
    }

    /**
     * Writes the CSS references to the output stream in-place.
     */
    public function generateCSS() {
        foreach ($this->css as $filePath) {
            echo "<link rel=\"stylesheet\" href=\"$filePath\" />\n";
        }
    }

    /**
     * Writes the JS references to the output stream in-place.
     */
    public function generateJavaScript() {
        foreach ($this->js as $filePath) {
            echo "<script src=\"$filePath\"></script>\n";
        }
    }
}
