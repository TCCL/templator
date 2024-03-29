# TCCL Template Generation Library

This project provides a simple PHP code library for generating pages from PHP template files.

## Installation

This library is available as a composer package. Require `tccl/templator` in your composer.json file and then install.

~~~shell
composer require tccl/templator
~~~

## Usage

Templator loads PHP template files from the include path. First make sure your include path is configured correctly. Alternatively you can load templates relative to the script directory. Template scripts must have some arbitrary extension. If not, the functionality attempts to use the extension `.php.tpl` by appending it to the end of the path.

The base interface `\TCCL\Templator\Templator` defines the contract for templators. The library provides a basic templator via the `\TCCL\Templator\TemplateGenerator` class and an extended version via the `\TCCL\Templator\PageGenerator` class. The `TemplateGenerator` is a generic
template generator and the `PageGenerator` is a more specialized version for top-level HTML pages. `PageGenerator` extends `TemplateGenerator`.

### Sample usage

~~~php
$page = new \TCCL\Templator\PageGenerator('login'); // i.e. login.php.tpl
$page->addVariable('failed',true);
$html = $page->evaluate();

echo $html;
~~~

Templators of type `TemplateGenerator` are designed to have nested components (which are themselves templators). These templators are executed in the context of a method call, meaning the `$this` variable always refers to the templator instance.

`TemplateGenerator` instances also store lists of variables which are extracted into the scope of the template script execution. Variables should be added in your model via addVariable() or addVariables(). Variables are imported from a parent templator into the scope of a child template script execution.

### Pre-evaluation

`TemplateGenerator` instances may be pre-evaluated by toggling a flag in the constructor call. When a template is pre-evaluated, it is executed preemptively when it is first added to a parent `TemplateGenerator`, or, in the case of a `PageGenerator`, when the object is first created. The variable `$this->preeval` is set to `true` in this case. If a pre-evaluated template script produces output, this output is kept in a memory cache for the lifetime of the templator.

> **Caution!** This could negatively affect performance if a template script produces a lot of output. On the other hand, a template script may return no output when pre-evaluated by use of a `return` statement. This is useful for performing some configuration task specific to that template (e.g. adding a CSS-file reference).

A script may toggle `$this->preeval` to `false` to generate output when the script is called again. This only works if the template script returns before producing any output since evaluations are cached.

### Example

Example template script using `PageGenerator` methods:

~~~php
<html>
  <head>
	<title><?php print $title;?></title>
  </head>
  <body>
	<?php $this->generateComponent('top-bar');?>
	<div class="core-content">
	  <?php $this->generateComponent('content');?>
	</div>
  </body>
</html>
~~~

Sample code creating such a `PageGenerator` (the templators are configured to avoid pre-evaluating HTML in memory):

~~~php
$page = new \TCCL\Templator\PageGenerator('index');
$page->addVariable('title','The Site');

$topbar = new \TCCL\Templator\TemplateGenerator('top-bar');
$page->addComponent('top-bar',$topbar);

$content = new \TCCL\Templator\TemplateGenerator('index-content');
$page->addComponent('content',$content);
~~~
