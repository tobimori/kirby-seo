<?php

use Kirby\Cms\App;
use Kirby\Cms\Page;
use Kirby\Cms\Pages;
use tobimori\Seo\Meta;
use tobimori\Seo\Seo;
use tobimori\Seo\Sitemap\SitemapIndex;

return function (SitemapIndex $sitemap) {
	$exclude = Seo::option('sitemap.excludeTemplates');
	$pages = App::instance()
		->site()
		->index()
		->filter(fn (Page $page) => !in_array($page->intendedTemplate()->name(), $exclude, true));

	/** @var iterable<string, Pages> $groups */
	$groups = Seo::option('sitemap.groupByTemplate') ? $pages->group('intendedTemplate') : ['pages' => $pages];

	foreach ($groups as $key => $group) {
		$index = null;

		foreach ($group as $page) {
			$urls = [];
			$alternates = [];
			foreach (App::instance()->multilang() ? App::instance()->languages() : [null] as $language) {
				// only if this language is translated for this page and exists
				if ($language && !$page->translation($language->code())->exists()) {
					continue;
				}

				$meta = $page->metadata($language);
				if (!$meta->robotsIndex()->toBool()) {
					continue;
				}

				$urls[] = [$meta->canonicalUrl(), $language?->code()];
				if ($language) {
					/*
					 * Specification: "lists every alternate version of the page, including itself."
					 * https://developers.google.com/search/docs/specialty/international/localized-versions#sitemap
					 */
					$alternates[] = ['rel' => 'alternate', 'hreflang' => Meta::toBCP47($language), 'href' => $meta->canonicalUrl()];
				}
			}

			if ($urls === []) {
				continue;
			}

			// add x-default
			if (count($alternates) > 1) {
				$alternates[] = ['rel' => 'alternate', 'hreflang' => 'x-default', 'href' => $page->indexUrl()];
			}

			$index ??= $sitemap->create($key);
			foreach ($urls as [$loc, $languageCode]) {
				$url = $index
					->createUrl($loc)
					->lastmod((int)($page->modified(languageCode: $languageCode) ?: time()))
					->changefreq(Seo::option('sitemap.changefreq', args: [$page]))
					->priority((string)Seo::option('sitemap.priority', args: [$page]));

				if (count($alternates) > 1) {
					$url->alternates($alternates);
				}
			}
		}
	}
};
