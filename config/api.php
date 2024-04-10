<?php

use Kirby\Cms\Page;
use Kirby\Cms\Site;
use Kirby\Form\Form;
use Kirby\Toolkit\Str;

return [
	'data' => [
		'dirtyPageOrSite' => function (string $slug) {
			$kirby = kirby();
			$page = $slug == 'site' ? $kirby->site() : $kirby->page(Str::replace($slug, '+', '/'));

			if ($this->requestBody()) {
				$form = Form::for($page, [ // Form class handles transformation of changed items
					'ignoreDisabled' => true,
					'input' => array_merge(['title' => $page->title()], $page->content()->data(), $this->requestBody()),
					'language' => $kirby->language()?->code()
				]);

				$page = $page->clone(['content' => $form->data()]);
			}

			return $page;
		}
	],
	'routes' =>  [
		[
			'pattern' => '/k-seo/(:any)/heading-structure',
			'method' => 'POST',
			'action' => function (string $slug) {
				$model = $this->dirtyPageOrSite($slug);

				if ($model instanceof Page) {
					$page = $model->render();
					$dom = new DOMDocument();
					$dom->loadHTML(htmlspecialchars_decode(mb_convert_encoding(htmlentities($page, ENT_COMPAT, 'UTF-8'), 'ISO-8859-1', 'UTF-8'), ENT_QUOTES), libxml_use_internal_errors(true));

					$xpath = new DOMXPath($dom);
					$headings = $xpath->query('//h1|//h2|//h3|//h4|//h5|//h6');
					$data = [];

					foreach ($headings as $heading) {
						$data[] = [
							'level' => (int)str_replace('h', '', $heading->nodeName),
							'text' => $heading->textContent,
						];
					}

					return $data;
				}

				return null;
			}
		],
		[
			'pattern' => '/k-seo/(:any)/seo-preview',
			'method' => 'POST',
			'action' => function (string $slug) {
				$model = $this->dirtyPageOrSite($slug);

				if ($model instanceof Site) {
					$model = $model->homePage();
				}

				if ($model instanceof Page) {
					$meta = $model->metadata();

					return [
						'page' => $model->slug(),
						'url' => $model->url(),
						'title' => $meta->metaTitle()->value(),
						'description' => $meta->metaDescription()->value(),
						'ogSiteName' => $meta->ogSiteName()->value(),
						'ogTitle' => $meta->ogTitle()->value(),
						'ogDescription' => $meta->ogDescription()->value(),
						'ogImage' => $meta->ogImage(),
						'twitterCardType' => $meta->twitterCardType()->value(),
					];
				}

				return null;
			}
		],
		[
			'pattern' => '/k-seo/(:any)/robots',
			'method' => 'POST',
			'action' => function (string $slug) {
				$model = $this->dirtyPageOrSite($slug);
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
