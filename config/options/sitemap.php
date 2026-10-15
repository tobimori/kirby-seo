<?php

use tobimori\Seo\Meta;
use tobimori\Seo\Sitemap\SitemapIndex;

return function (SitemapIndex $sitemap) {
	$exclude = option('tobimori.seo.sitemap.excludeTemplates', []);
	$pages = site()->index()->filter(fn ($page) => !in_array($page->intendedTemplate()->name(), $exclude));

	if ($group = option('tobimori.seo.sitemap.groupByTemplate')) {
		$pages = $pages->group('intendedTemplate');
	}

	if (is_a($pages->first(), 'Kirby\Cms\Page')) {
		$pages = $pages->group(fn () => 'pages');
	}

	foreach ($pages as $group) {
		$index = null;

		foreach ($group as $page) {
			$urls = [];
			$alternates = [];
			foreach (kirby()->multilang() ? kirby()->languages() : [null] as $language) {
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

			$index ??= $sitemap->create($group ? $group->first()->intendedTemplate()->name() : 'pages');
			foreach ($urls as [$loc, $languageCode]) {
				$url = $index
					->createUrl($loc)
					->lastmod($page->modified(languageCode: $languageCode) ?? time())
					->changefreq(is_callable($changefreq = option('tobimori.seo.sitemap.changefreq')) ? $changefreq($page) : $changefreq)
					->priority(is_callable($priority = option('tobimori.seo.sitemap.priority')) ? $priority($page) : $priority);

				if (count($alternates) > 1) {
					$url->alternates($alternates);
				}
			}
		}
	}
};
