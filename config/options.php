<?php

use Kirby\Cms\Page;
use tobimori\Seo\Meta;
use tobimori\Seo\Seo;
use tobimori\Seo\Ai;
use tobimori\Seo\IndexNow;
use tobimori\Seo\SchemaSingleton;

return [
	// if you want to extend some of the built-in classes, you can overwrite them using the components config option
	// and page methods or similar stuff will adapt. full customizability!
	'components' => [
		'meta' => Meta::class,
		'ai' => Ai::class,
		'indexnow' => IndexNow::class,
		'schema' => SchemaSingleton::class,
	],
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
		'ogDescription' => fn (Page $page) => $page->metadata()->metaDescription(),
		'cropOgImage' => true,
		'locale' => fn (Page $page) => $page->kirby()->language()?->locale(LC_ALL) ?? Seo::option('locale', 'en_US'),
		// default for robots: noIndex if global index configuration is set, otherwise fall back to page status
		'robotsIndex' => function (Page $page) {
			$index = Seo::option('robots.index');
			if (!$index) {
				return false;
			}

			return Seo::option('robots.followPageStatus') ? $page->isListed() : true;
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
		'bluesky' => 'https://bsky.app/profile/example.bsky.social',
		'mastodon' => 'https://mastodon.social/@example'
	],
	'previews' => [
		'google',
		'facebook',
		'slack'
	],
	'robots' => [
		'enabled' => true, // whether robots handling should be done by the plugin

		// @deprecated - please use robots.enabled
		'active' => fn () => Seo::option('sitemap.enabled'),
		'followPageStatus' => true, // should unlisted pages be noindex by default?
		'pageSettings' => true, // whether to have robots settings on each page
		'index' => fn () => !option('debug'), // default site-wide robots setting
		'sitemap' => null, // sets sitemap url, will be replaced by plugin sitemap in the future
		'content' => [], // custom robots content
		'types' => ['index', 'follow', 'archive', 'imageindex', 'snippet'] // available robots types
	],
	'sitemap' => [
		'enabled' => true,
		// @deprecated - please use sitemap.enabled
		'active' => fn () => Seo::option('sitemap.enabled'),
		'redirect' => true, // redirect /sitemap to /sitemap.xml
		'locale' => 'en',
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
	'canonical' => [
		'base' => null, // base url for canonical links
		'trailingSlash' => false, // whether to add trailing slashes to canonical URLs (except for files)
	],
	'ai' => require __DIR__ . '/options/ai.php',
	'indexnow' => [
		'enabled' => true,
		'searchEngine' => 'https://api.indexnow.org' // one will propagate to all others. so this is fine @see https://www.indexnow.org/faq
		// TODO: add batch job delay
		// TODO: add propagation thing (i.e. do not only submit the current page but a 'team member' page will always affect the 'team' page)
	],
	'generateSchema' => true, // whether to generate default schema.org data
	'locale' => 'en_US', // default locale, used for single-language sites
	'dateFormat' => null, // custom date format
];
