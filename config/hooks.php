<?php

use Kirby\Cms\Page;
use Kirby\Toolkit\A;
use Kirby\Toolkit\Str;

return [
	'page.update:after' => function (Page $newPage, Page $oldPage) {
		$updates = A::reduce(
			$newPage->kirby()->option('tobimori.seo.robots.types'),
			function ($carry, $robots) use ($newPage) {
				$upper = Str::ucfirst($robots);

				if ($newPage->content()->get("robots{$upper}")->value() === '') {
					$carry["robots{$upper}"] = 'default';
				}

				return $carry;
			},
			[]
		);

		$newPage = $newPage->update($updates);

		return $newPage;
	},
	'page.render:before' => function (string $contentType, array $data, Page $page) {
		if (!class_exists('Spatie\SchemaOrg\Schema')) {
			return;
		}

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
