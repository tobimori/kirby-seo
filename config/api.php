<?php

use Kirby\Cms\Page;
use Kirby\Cms\Site;
use Kirby\Form\Form;
use Kirby\Toolkit\Str;

return [
	'routes' =>  [
		[
			'pattern' => '/k-seo/(:any)/robots',
			'method' => 'POST',
			'action' => function (string $slug) {
				$kirby = kirby();
				$model = $slug == 'site' ? $kirby->site() : $kirby->page(Str::replace($slug, '+', '/'));

				// In Kirby 5, use the changes version if it exists
				$changesVersion = $model->version('changes');
				if ($changesVersion->exists('current')) {
					// Clone the page with the content from the changes version
					$model = $model->clone(['content' => $changesVersion->content()->toArray()]);
				}

				if (!($model instanceof Page)) {
					return null;
				}
				$robots = $model->robots();

				return [
					'active' => option('tobimori.seo.robots.indicator', option('tobimori.seo.robots.active', true)),
					'state' => $robots,
				];
			}
		]
	]
];
