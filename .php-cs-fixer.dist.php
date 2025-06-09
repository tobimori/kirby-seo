<?php

$finder = PhpCsFixer\Finder::create()
	->exclude('vendor')
	->in(__DIR__);

$config = new PhpCsFixer\Config();
return $config
	->setRules([
		'@PSR12' => true,
		'array_indentation' => true,
	])
	->setRiskyAllowed(true)
	->setIndent("\t")
	->setFinder($finder);
