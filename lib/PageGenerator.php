<?php

/**
 * PageGenerator.php
 *
 * This file is a part of tccl/templator.
 *
 * @package tccl/templator
 */

namespace TCCL\Templator;

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
