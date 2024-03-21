<?php

use Kirby\Cms\Page;
use Kirby\Http\Response;
use tobimori\Seo\Sitemap\SitemapIndex;

return [
	[
		'pattern' => 'robots.txt',
		'action' => function () {
			if (option('tobimori.seo.robots.active', true)) {
				$content = snippet('seo/robots.txt', [], true);
				return new Response($content, 'text/plain', 200);
			}

			$this->next();
		}
	],
	[
		'pattern' => 'sitemap',
		'action' => function () {
			if (!option('tobimori.seo.sitemap.redirect', true) || !option('tobimori.seo.sitemap.active', true)) {
				$this->next();
			}

			go('/sitemap.xml');
		}
	],
	[
		'pattern' => 'sitemap.xsl',
		'action' => function () {
			if (!option('tobimori.seo.sitemap.active', true)) {
				$this->next();
			}

			kirby()->response()->type('text/xsl');

			$lang = option('tobimori.seo.sitemap.lang', 'en');
			if (is_callable($lang)) {
				$lang = $lang();
			}
			kirby()->setCurrentTranslation($lang);

			return Page::factory([
				'slug' => 'sitemap',
				'template' => 'sitemap',
				'model' => 'sitemap',
				'content' => [
					'title' => t('sitemap'),
				],
			])->render(contentType: 'xsl');
		}
	],
	[
		'pattern' => 'sitemap.xml',
		'action' => function () {
			if (!option('tobimori.seo.sitemap.active', true)) {
				$this->next();
			}

			SitemapIndex::instance()->generate();
			kirby()->response()->type('text/xml');
			return Page::factory([
				'slug' => 'sitemap',
				'template' => 'sitemap',
				'model' => 'sitemap',
				'content' => [
					'title' => t('sitemap'),
					'index' => null,
				],
			])->render(contentType: 'xml');
		}
	],
	[
		'pattern' => 'sitemap-(:any).xml',
		'action' => function (string $index) {
			if (!option('tobimori.seo.sitemap.active', true)) {
				$this->next();
			}

			SitemapIndex::instance()->generate();
			if (!SitemapIndex::instance()->isValidIndex($index)) {
				$this->next();
			}

			kirby()->response()->type('text/xml');
			return Page::factory([
				'slug' => 'sitemap-' . $index,
				'template' => 'sitemap',
				'model' => 'sitemap',
				'content' => [
					'title' => t('sitemap'),
					'index' => $index,
				],
			])->render(contentType: 'xml');
		}
	]
];
