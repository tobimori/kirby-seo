<?php

use tobimori\Seo\Sitemap\SitemapIndex;
use tobimori\Seo\Meta;

return function (SitemapIndex $sitemap) {
	$exclude = option('tobimori.seo.sitemap.excludeTemplates', []);
	$pages = site()->index()->filter(fn ($page) => $page->metadata()->robotsIndex()->toBool() && !in_array($page->intendedTemplate()->name(), $exclude));

	if ($group = option('tobimori.seo.sitemap.groupByTemplate')) {
		$pages = $pages->group('intendedTemplate');
	}

	if (is_a($pages->first(), 'Kirby\Cms\Page')) {
		$pages = $pages->group(fn () => 'pages');
	}

	foreach ($pages as $group) {
		$index = $sitemap->create($group ? $group->first()->intendedTemplate()->name() : 'pages');

		foreach ($group as $page) {
			$url = $index->createUrl($page->metadata()->canonicalUrl())
				->lastmod($page->modified() ?? (int)(date('c')))
				->changefreq(is_callable($changefreq = option('tobimori.seo.sitemap.changefreq')) ? $changefreq($page) : $changefreq)
				->priority(is_callable($priority = option('tobimori.seo.sitemap.priority')) ? $priority($page) : $priority);

			if (kirby()->languages()->count() > 1 && kirby()->language() !== null) {
				$alternates = [];
				foreach (kirby()->languages() as $language) {
					// only if this language is translated for this page and exists
					if ($page->translation($language->code())->exists()) {
						/*
						 * Specification: "lists every alternate version of the page, including itself."
						 * https://developers.google.com/search/docs/specialty/international/localized-versions#sitemap
						 */
						$alternates[] =
							[
								'hreflang' => Meta::toBCP47($language),
								'href' => $page->url($language->code()),
							];
					}
				}

				// add x-default
				$alternates[] =
					[
						'hreflang' => 'x-default',
						'href' => $page->indexUrl(),
					];

				$url->alternates($alternates);
			}
		}
	}
};
