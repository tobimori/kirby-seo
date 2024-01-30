<?php

use Kirby\Toolkit\Obj;
use tobimori\Seo\Sitemap\SitemapIndex;

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
				$url->alternates(
					kirby()->languages()->map(fn ($language) => new Obj([
						'hreflang' => $language->code() === kirby()->language()->code() ? 'x-default' : $language->code(),
						'href' => $page->url($language->code()),
					]))->toArray()
				);
			}
		}
	}
};
