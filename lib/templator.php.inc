<?php

/**
 * templator.php.inc
 *
 * This library provides a simple HTML template generator.
 *
 * @package templator
 */

use TCCL\Templator\ContentCache;

/**
 * The base interface that describes a template generator
 */
interface Templator {
    /**
     * Evaluates the content of the template page and returns it as a string
     *
     * @return string
     */
    public function evaluate();

    /**
     * Writes the evaluate content to the output stream (this does what
     * evaluate() does except it echos it to the output stream instead of
     * returning it).
     */
    public function generate();
}

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
        $component->parent = $this;
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
        echo $this->evaluate();
    }
}

/**
 * A specific template generator with convenience functions for generating a
 * top-level HTML page. This templator lets you add stylesheet and script
 * references to the template.
 *
 * References are specified in reverse order since nested scripts can
 * recursively add references that depend on references added in a base
 * template. This means independent references should be specified after
 * dependent ones.
 *
 * A cache configuration is provided for js/css files. This works by converting
 * files into a cached representation and storing them somewhere on disk. Each
 * cache file is represented by its unique content hash. An index file is used
 * to maintain mappings of resource to cached item.
 */
class PageGenerator extends TemplateGenerator {
    /**
     * The ContentCache instance to use when caching js/css files.
     *
     * @var \Templator\ContentCache
     */
    static private $cache = null;

    /**
     * An array of CSS stylesheets to add to the page. This array will be
     * reversed due to how the templates are evaluated. This is because we want
     * inner content to specify references first but be ordered later.
     *
     * @var array
     */
    private $css = array();

    /**
     * An array of JavaScript files to add to the page. This array will be
     * reversed due to how the templates are evaluated. This is because we want
     * inner content to specify references first but be evaluated later.
     *
     * @var array
     */
    private $js = array();

    /**
     * Add a stylesheet to the generator's list of stylesheets. This just saves
     * the reference to be written at a later time.
     *
     * @param string $filePath
     *  The path to the CSS file
     */
    public function addStylesheet($filePath) {
        if (is_object(self::$cache)) {
            $this->css[] = self::$cache->convertToCache($filePath,'css');
        }
        else {
            $this->css[] = $filePath;
        }
    }

    /**
     * Add a JavaScript file to the generator's list of JavaScript
     * references. This just saves the reference to be written at a later time.
     *
     * @param string $filePath
     *  The path to the JavaScript file
     */
    public function addJavaScript($filePath) {
        if (is_object(self::$cache)) {
            $this->js[] = self::convertToCache($filePath,'js');
        }
        else {
            $this->js[] = $filePath;
        }
    }

    /**
     * Writes the stored CSS references in-place to the output stream.
     */
    public function generateCSS() {
        foreach (array_reverse($this->css) as $filePath) {
            $this->css($filePath);
        }
    }

    /**
     * Writes the stored JS references in-place to the output stream.
     */
    public function generateJavaScript() {
        foreach (array_reverse($this->js) as $filePath) {
            $this->js($filePath);
        }
    }

    /**
     * Directly writes the specified JS reference in-place to the output
     * stream. The reference may be modified if caching is enabled.
     *
     * @param string $filePath
     *  The reference as it will be presented to the user-agent in the HTML
     *  source.
     */
    public function js($filePath) {
        if (is_object(self::$cache)) {
            $filePath = self::$cache->convertToCache($filePath,'js');
        }
        echo "<script src=\"$filePath\"></script>\n";
    }

    /**
     * Directly writes the specified JS reference in-place to the output
     * stream. The reference may be modified if caching is enabled.
     *
     * @param string $filePath
     *  The reference as it will be presented to the user-agent in the HTML
     *  source.
     */
    public function css($filePath) {
        if (is_object(self::$cache)) {
            $filePath = self::$cache->convertToCache($filePath,'css');
        }
        echo "<link rel=\"stylesheet\" href=\"$filePath\" />\n";
    }

    /**
     * Sets the cache policy for all PageGenerator instances.
     *
     * See \Templator\ContentCache::__construct for full documentation.
     */
    static public function setCachePolicy($cacheDir,$contentDir = '',$hooks = null) {
        self::$cache = new ContentCache($cacheDir,$contentDir,$hooks);
    }
}

// Create an autoloader for any Templator auxiliary classes.
spl_autoload_register(function($class){
    switch ($class) {
    case 'TCCL\Templator\ContentCache':
        require_once 'contentcache.php.inc';
        break;
    }
});
