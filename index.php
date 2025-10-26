<?php

@include_once __DIR__ . '/vendor/autoload.php';

use Kirby\Cms\App;
use Kirby\Data\Json;
use Spatie\SchemaOrg\Schema;
use Kirby\Toolkit\A;
use Kirby\Filesystem\Dir;
use Kirby\Filesystem\F;

if (
	version_compare(App::version() ?? '0.0.0', '5.0.0', '<') === true ||
	version_compare(App::version() ?? '0.0.0', '6.0.0', '>=') === true
) {
	throw new Exception('Kirby SEO requires Kirby 5');
}

App::plugin(
	'tobimori/seo',
	// TODO: license
	extends: [
		'options' => require __DIR__ . '/config/options.php',
		'sections' => require __DIR__ . '/config/sections.php',
		'areas' => require __DIR__ . '/config/areas.php',
		'siteMethods' => require __DIR__ . '/config/site-methods.php',
		'pageMethods' => require __DIR__ . '/config/page-methods.php',
		'hooks' => require __DIR__ . '/config/hooks.php',
		'routes' => require __DIR__ . '/config/routes.php',
		'fields' => require __DIR__ . '/config/fields.php',
		'permissions' => [
			'ai' => true,
		],
		'snippets' => [
			'seo/prompts/introduction' => __DIR__ . '/snippets/prompts/introduction.php',
			'seo/prompts/content' => __DIR__ . '/snippets/prompts/content.php',
			'seo/prompts/meta' => __DIR__ . '/snippets/prompts/meta.php',
			'seo/prompts/site-meta' => __DIR__ . '/snippets/prompts/site-meta.php',
			'seo/prompts/tasks/title' => __DIR__ . '/snippets/prompts/tasks/title.php',
			'seo/prompts/tasks/description' => __DIR__ . '/snippets/prompts/tasks/description.php',
			'seo/prompts/tasks/og-description' => __DIR__ . '/snippets/prompts/tasks/og-description.php',
			'seo/prompts/tasks/site-description' => __DIR__ . '/snippets/prompts/tasks/site-description.php',
			'seo/prompts/tasks/og-site-description' => __DIR__ . '/snippets/prompts/tasks/og-site-description.php',
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
			'seo/fields/title-template' => __DIR__ . '/blueprints/fields/title-template.yml',
			'seo/fields/robots' => require __DIR__ . '/blueprints/fields/robots.php',
			'seo/fields/site-robots' => require __DIR__ . '/blueprints/fields/site-robots.php',
			'seo/fields/social-media' => require __DIR__ . '/blueprints/fields/social-media.php',
		],
		// get all files from /translations and register them as language files
		'translations' => A::keyBy(
			A::map(
				Dir::files(__DIR__ . '/translations'),
				function ($file) {
					$translations = [];
					foreach (Json::read(__DIR__ . '/translations/' . $file) as $key => $value) {
						$translations["seo.{$key}"] = $value;
					}

					return A::merge(
						['lang' => F::name($file)],
						$translations
					);
				}
			),
			'lang'
		),
	]
);

if (!function_exists('schema')) {
	function schema($type)
	{
		if (!class_exists('Spatie\SchemaOrg\Schema')) {
			return null;
		}

		return Schema::{$type}();
	}
}
