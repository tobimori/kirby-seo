<?php

@include_once __DIR__ . '/vendor/autoload.php';

use Kirby\Cms\App;
use Kirby\Data\Yaml;
use Spatie\SchemaOrg\Schema;

if (
	version_compare(App::version() ?? '0.0.0', '5.0.0-rc.1', '<') === true ||
	version_compare(App::version() ?? '0.0.0', '6.0.0', '>') === true
) {
	throw new Exception('Kirby SEO requires Kirby 5');
}

App::plugin('tobimori/seo', [
	'options' => require __DIR__ . '/config/options.php',
	'sections' => require __DIR__ . '/config/sections.php',
	'api' =>  require __DIR__ . '/config/api.php',
	'siteMethods' => require __DIR__ . '/config/siteMethods.php',
	'pageMethods' => require __DIR__ . '/config/pageMethods.php',
	'hooks' => require __DIR__ . '/config/hooks.php',
	'routes' => require __DIR__ . '/config/routes.php',
	'commands' => [
		'seo:hello' => require __DIR__ . '/config/commands/hello.php',
	],
	'translations' => [
		'cs' => Yaml::read(__DIR__ . '/translations/cs.yml'),
		'de' => Yaml::read(__DIR__ . '/translations/de.yml'),
		'en' => Yaml::read(__DIR__ . '/translations/en.yml'),
		'fr' => Yaml::read(__DIR__ . '/translations/fr.yml'),
		'nl' => Yaml::read(__DIR__ . '/translations/nl.yml'),
		'pt_PT' => Yaml::read(__DIR__ . '/translations/pt_PT.yml'),
		'sv_SE' => Yaml::read(__DIR__ . '/translations/sv_SE.yml'),
		'tr' => Yaml::read(__DIR__ . '/translations/tr.yml'),
	],
	'snippets' => [
		'seo/schemas' => __DIR__ . '/snippets/schemas.php',
		'seo/head' => __DIR__ . '/snippets/head.php',
		'seo/robots.txt' => __DIR__ . '/snippets/robots.txt.php',
	],
	'templates' => [
		'sitemap' => __DIR__ . '/templates/sitemap.php',
		'sitemap.xml' => __DIR__ . '/templates/sitemap.xml.php',
		'sitemap.xsl' => __DIR__ . '/templates/sitemap.xsl.php',
	],
	'blueprints' => [
		'seo/site' => __DIR__ . '/blueprints/site.yml',
		'seo/page' => __DIR__ . '/blueprints/page.yml',
		'seo/fields/og-image' => require __DIR__ . '/blueprints/fields/og-image.php',
		'seo/fields/og-group' => __DIR__ . '/blueprints/fields/og-group.yml',
		'seo/fields/meta-group' => __DIR__ . '/blueprints/fields/meta-group.yml',
		'seo/fields/robots' => require __DIR__ . '/blueprints/fields/robots.php',
		'seo/fields/site-robots' => require __DIR__ . '/blueprints/fields/site-robots.php',
		'seo/fields/social-media' => require __DIR__ . '/blueprints/fields/social-media.php',
	],
]);

if (!function_exists('schema')) {
	function schema($type)
	{
		if (!class_exists('Spatie\SchemaOrg\Schema')) {
			return null;
		}

		return Schema::{$type}();
	}
}
