<?php

use Kirby\Cms\Page;

return [
	'page.render:before' => function (string $contentType, array $data, Page $page) {
		if (option('tobimori.seo.generateSchema')) {
			$page->schema('WebSite')->url($page->metadata()->canonicalUrl())->copyrightYear(date('Y'))->description($page->metadata()->metaDescription())->name($page->metadata()->metaTitle())->headline($page->metadata()->title());
		}
	}
];
