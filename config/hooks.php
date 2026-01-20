<?php

use Kirby\Cms\Page;
use Kirby\Toolkit\A;
use Kirby\Toolkit\Str;
use tobimori\Seo\Seo;

return [
	'page.update:after' => function (Page $newPage, Page $oldPage) {
		// only inject blueprint defaults if the seo tab is present
		if ($newPage->blueprint()->tab('seo')) {
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

			if (A::count($updates)) {
				$newPage = $newPage->update($updates, $newPage->kirby()->languageCode());
			}
		}

		if (Seo::option('indexnow.enabled')) {
			$indexNow = new (Seo::option('components.indexnow'))($newPage);
			$indexNow->request();
		}

		return $newPage;
	},
	'page.changeStatus:after' => function (Page $newPage, Page $oldPage) {
		if (Seo::option('indexnow.enabled')) {
			$indexNow = new (Seo::option('components.indexnow'))($newPage);
			$indexNow->request();
		}
	},
	'page.changeSlug:after' => function (Page $newPage, Page $oldPage) {
		if (Seo::option('indexnow.enabled')) {
			$indexNow = new (Seo::option('components.indexnow'))($newPage);
			$indexNow->request();
		}
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
