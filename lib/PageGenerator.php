<?php

/**
 * PageGenerator.php
 *
 * This file is a part of tccl/templator.
 *
 * @package tccl/templator
 */

namespace TCCL\Templator;

use Exception;

/**
 * PageGenerator
 *
 * A specific template generator with convenience functions for generating a
 * top-level HTML page. This templator lets you add stylesheet and script paths
 * to the template and will generate the appropriate references in the resulting
 * markup. This templator adds a variable called 'basePage' to every
 * templator. This is a reference to the PageGenerator object itself.
 *
 * CSS/JS references are generated in reverse order since nested scripts can
 * recursively add references that depend on references added in a base
 * template. This means independent references should be specified after
 * dependent ones.
 *
 * A ContentCache may be set on this templator, which is used for transforming
 * CSS/JS files through a versioned cache layer.
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
     * Constructs a new PageGenerator instance.
     *
     * @param string $basePage
     *  The file path of the page template file
     * @param bool $preeval
     *  Determines if the templator is configured to pre-evaluate its content.
     */
    public function __construct($basePage,$preeval = false) {
        parent::__construct($basePage,$preeval);

        // Add a reference to ourself called "basePage".
        $this->addVariable('basePage',$this);

        if ($preeval) {
            $this->evaluate();
        }
    }

    /**
     * Add a stylesheet to the generator's list of stylesheets. This just saves
     * the reference to be written at a later time.
     *
     * @param string $filePath
     *  The path to the CSS file
     */
    public function addStylesheet($filePath) {
        $this->css[] = $filePath;
    }

    /**
     * Alias for PageGenerator::addStylesheet().
     */
    public function addCSS($filePath) {
        $this->css[] = $filePath;
    }

    /**
     * Add a JavaScript file to the generator's list of JavaScript
     * references. This just saves the reference to be written at a later time.
     *
     * @param string $filePath
     *  The path to the JavaScript file
     */
    public function addJavaScript($filePath) {
        $this->js[] = $filePath;
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
     * Alias for PageGenerator::generateCSS().
     */
    public function generateStylesheets() {
        $this->generateCSS();
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
     * Overrides TemplateGenerator::inherit().
     */
    public function inherit(Templator $tpl) {
        throw new Exception("A PageGenerator instance cannot be used as a child Templator");
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
