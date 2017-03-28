<?php

/**
 * Templator.php
 *
 * This library provides a simple HTML template generator.
 *
 * @package tccl/templator
 */

namespace TCCL\Templator;

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

    /**
     * Allow child templates to perform tasks with awareness of their parent
     * templators.
     */
    public function inherit(Templator $parent);
}
