<?php

/**
 * contentcache.php.inc
 *
 * This file is a part of templator. You should not include this file
 * directly. It contains functionality used by the templator implementation.
 *
 * @package templator
 */

namespace TCCL\Templator;

use Exception;

class ContentCache {
    /**
     * Directory in which cache files are written.
     */
    private $cacheDir;

    /**
     * Current directory for cachable content. Any relative path is evaluated
     * relative to this directory.
     */
    private $cacheContentDir;

    /**
     * An associative array mapping content type identifiers to a command that
     * filters the cache content. Each entry may map to a bucket if a command
     * pipeline is required.
     */
    private $cacheHooks = null;

    /**
     * Creates a new ContentCache instance.
     *
     * @param string $cacheDir
     *  The path to the cache directory. This path specification must be
     *  relative to the document root, exist in the filesystem and be writable
     *  by the Web server process.
     * @param string $contentDir
     *  The path, relative to the Web document root, used to evaluate relative
     *  content paths.
     * @param array $hooks
     *  A table mapping content identifiers to command-lines used to filter the
     *  content before it is cached. The following valid content types are
     *  allowed:
     *   - "js": JavaScript
     *   - "css": Cascading Style Sheets
     *  Each entry may map to a single command-line or an array of
     *  command-lines. In the case of the later, the commands will be executed
     *  in a pipeline in the standard way. The following special tokens are
     *  substituted in command-lines:
     *   - "%src%" - absolute path to source file name
     */
    public function __construct($cacheDir,$contentDir = '',$hooks = null) {
        if (empty($cacheDir)) {
            throw new Exception(__METHOD__.': cache policy is incorrect');
        }

        // Normalize the content directory specification to have a leading path
        // separator.
        if (empty($contentDir)) {
            $contentDir = '/';
        }
        else if ($contentDir[0] != '/') {
            $contentDir = "/$contentDir";
        }

        $this->cacheDir = $cacheDir;
        $this->cacheContentDir = $contentDir;
        $this->cacheHooks = $hooks;
    }

    /**
     * This function does the hard work of caching a file.
     *
     * @param string $filePath
     *  The path of the file being cached, as seen by the user-agent. (This is
     *  important so we can make consistent lookups).
     * @param string $type
     *  The type of content the file contains (so as to invoke the correct
     *  hooks).
     *
     * @return string
     *  The file path to the cached version.
     */
    public function convertToCache($filePath,$type = '') {
        return $filePath;
    }
}
