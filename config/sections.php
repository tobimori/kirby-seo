<?php

use Kirby\Cms\Page;
use Kirby\Cms\Site;
use Kirby\Toolkit\A;

return [
	'seo-preview' => [
		'mixins' => ['headline'],
		'computed' => [
			'options' => function () {
				return A::map(option('tobimori.seo.previews'), fn($item) => [
					'value' => $item,
					'text' => t($item)
				]);
			},
			'meta' => function () {
				$model = $this->model();

				if ($model instanceof Site || $model instanceof Page) {
					// Clone the model with the content from the changes version
					$changesVersion = $model->version('changes');
					if ($changesVersion->exists('current')) {
						$model = $model->clone(['content' => $changesVersion->content()->toArray()]);
					}

					// For display purposes, if it's a site, show the home page URL
					$displayModel = $model instanceof Site ? $model->homePage() : $model;
					$meta = $model->metadata();

					return [
						'page' => $displayModel ? $displayModel->slug() : '',
						'url' => $displayModel ? $displayModel->url() : '',
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
		]
	],
	'heading-structure' => [
		'mixins' => ['headline'],
		'computed' => [
			'data' => function () {
				// Get the parent model
				$model = $this->model();

				// This section only works for pages (not site)
				if (!($model instanceof Page)) {
					return [];
				}

				// In Kirby 5, use the changes version if it exists
				$changesVersion = $model->version('changes');
				if ($changesVersion->exists('current')) {
					// Clone the page with the content from the changes version
					$model = $model->clone(['content' => $changesVersion->content()->toArray()]);
				}

				// Render the page
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
		]
	]
];
