<?php

@include_once __DIR__ . '/vendor/autoload.php';

use Kirby\Cms\App;
use Kirby\Data\Yaml;
use Kirby\Filesystem\Dir;
use Kirby\Filesystem\F;
use Kirby\Toolkit\A;
use Spatie\SchemaOrg\Schema;

// shamelessly borrowed from distantnative/retour-for-kirby
if (
	version_compare(App::version() ?? '0.0.0', '4.0.2', '<') === true ||
	version_compare(App::version() ?? '0.0.0', '5.0.0', '>') === true
) {
	throw new Exception('Kirby SEO requires Kirby 4.0.2 or higher.');
}

App::plugin('tobimori/seo', [
	'options' => require __DIR__ . '/config/options.php',
	'sections' => require __DIR__ . '/config/sections.php',
	'api' =>  require __DIR__ . '/config/api.php',
	'siteMethods' => require __DIR__ . '/config/siteMethods.php',
	'pageMethods' => require __DIR__ . '/config/pageMethods.php',
	'hooks' => require __DIR__ . '/config/hooks.php',
	'routes' => require __DIR__ . '/config/routes.php',
	// load all commands automatically
	'commands' => A::keyBy(A::map(
		Dir::read(__DIR__ . '/config/commands'),
		fn ($file) => A::merge([
			'id' => 'seo:' . F::name($file),
		], require __DIR__ . '/config/commands/' . $file)
	), 'id'),
	// get all files from /translations and register them as language files
	'translations' => A::keyBy(A::map(
		Dir::read(__DIR__ . '/translations'),
		fn ($file) => A::merge([
			'lang' => F::name($file),
		], Yaml::decode(F::read(__DIR__ . '/translations/' . $file)))
	), 'lang'),
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
		return Schema::{$type}();
	}
}
