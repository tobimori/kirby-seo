<?php

use Kirby\Cms\Page;

return [
	'cascade' => [
		'fields',
		'programmatic',
		// 'fallbackFields', // fallback to meta fields for open graph fields
		'parent',
		'site',
		'options'
	],
	'default' => [ // default field values for metadata, format is [field => value]
		'metaTitle' => fn (Page $page) => $page->title(),
		'metaTemplate' => '{{ title }} - {{ site.title }}',
		'ogTemplate' => '{{ title }}',
		'ogSiteName' => fn (Page $page) => $page->site()->title(),
		'ogType' => 'website',
		'twitterCardType' => 'summary',
		'ogDescription' => fn (Page $page) => $page->metadata()->metaDescription(),
		'twitterCreator' => fn (Page $page) => $page->metadata()->twitterSite(),
		'lang' => fn (Page $page) => $page->kirby()->language()?->locale(LC_ALL) ?? $page->kirby()->option('tobimori.seo.lang', 'en_US'),
		// default for robots: noIndex if global index configuration is set, otherwise fall back to page status
		'robotsIndex' => function (Page $page) {
			$index = $page->kirby()->option('tobimori.seo.robots.index');
			if (is_callable($index)) {
				$index = $index();
			}

			if (!$index) {
				return false;
			}

			return $page->kirby()->option('tobimori.seo.robots.followPageStatus', true) ? $page->isListed() : true;
		},
		'robotsFollow' => fn (Page $page) => $page->kirby()->option('tobimori.seo.default.robotsIndex')($page),
		'robotsArchive' => fn (Page $page) => $page->kirby()->option('tobimori.seo.default.robotsIndex')($page),
		'robotsImageindex' => fn (Page $page) => $page->kirby()->option('tobimori.seo.default.robotsIndex')($page),
		'robotsSnippet' => fn (Page $page) => $page->kirby()->option('tobimori.seo.default.robotsIndex')($page),
	],
	'socialMedia' => [ // default fields for social media links, format is [field => placeholder]
		'twitter' => 'https://twitter.com/my-company',
		'facebook' => 'https://facebook.com/my-company',
		'instagram' => 'https://instagram.com/my-company',
		'youtube' => 'https://youtube.com/channel/my-company',
		'linkedin' => 'https://linkedin.com/company/my-company',
	],
	'previews' => [
		'google',
		'facebook',
		'slack'
	],
	'robots' => [
		'active' => true, // whether robots handling should be done by the plugin
		'followPageStatus' => true, // should unlisted pages be noindex by default?
		'pageSettings' => true, // whether to have robots settings on each page
		'indicator' => true, // whether the indicator should be shown in the panel
		'index' => fn () => !option('debug'), // default site-wide robots setting
		'sitemap' => null, // sets sitemap url, will be replaced by plugin sitemap in the future
		'content' => [], // custom robots content
		'types' => ['index', 'follow', 'archive', 'imageindex', 'snippet'] // available robots types
	],
	'sitemap' => [
		'active' => true,
		'redirect' => true, // redirect /sitemap to /sitemap.xml
		'lang' => 'en',
		'generator' => require __DIR__ . '/options/sitemap.php',
		'changefreq' => 'weekly',
		'groupByTemplate' => false,
		'excludeTemplates' => ['error'],
		'priority' => fn (Page $p) => number_format(($p->isHomePage()) ? 1 : max(1 - 0.2 * $p->depth(), 0.2), 1),
	],
	'files' => [
		'parent' => null,
		'template' => null,
	],
	'canonicalBase' => null, // base url for canonical links
	'generateSchema' => true, // whether to generate default schema.org data
	'lang' => 'en_US', // default language, used for single-language sites
	'dateFormat' => null, // custom date format
];
