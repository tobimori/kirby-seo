<?php

use Kirby\Cms\Page;
use Kirby\Toolkit\Str;

return [
	'page.update:after' => function (Page $newPage, Page $oldPage) {
		foreach ($newPage->kirby()->option('tobimori.seo.robots.types') as $robots) {
			$upper = Str::ucfirst($robots);
			if ($newPage->content()->get("robots{$upper}")->value() === "") {
				$newPage = $newPage->update([
					"robots{$upper}" => 'default'
				]);
			}
		}
	},
	'page.render:before' => function (string $contentType, array $data, Page $page) {
		if (option('tobimori.seo.generateSchema')) {
			$page->schema('WebSite')
				->url($page->metadata()->canonicalUrl())
				->copyrightYear(date('Y'))
				->description($page->metadata()->metaDescription())
				->name($page->metadata()->metaTitle())
				->headline($page->metadata()->title());
		}
	},
];
