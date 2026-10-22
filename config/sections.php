<?php

use Kirby\Cms\App;
use Kirby\Cms\Page;
use Kirby\Cms\Section;
use Kirby\Cms\Site;
use Kirby\Toolkit\A;
use Kirby\Toolkit\I18n;
use Kirby\Toolkit\Str;
use tobimori\Seo\License;
use tobimori\Seo\Seo;

return [
	'seo-preview' => [
		'mixins' => ['headline'],
		'computed' => [
			'options' => fn () => A::map(Seo::option('previews'), fn ($item) => [
				'value' => $item,
				'text' => I18n::translate("seo.sections.preview.{$item}")
			]),
			'meta' => function () {
				/** @var Section $this */
				$model = $this->model();

				if ($model instanceof Site || $model instanceof Page) {
					// clone the model with the content from the changes version
					$changesVersion = $model->version('changes');
					if ($changesVersion->exists('current')) {
						$model = $model->clone(['content' => $changesVersion->content()->toArray()]);
					}

					// if it's a site, fall back to the home page for preview data
					$model = $model instanceof Site ? $model->homePage() : $model;
					if (!$model) {
						return null;
					}

					$meta = $model->metadata();
					return [
						'page' => $model->slug(),
						'url' => $meta->canonicalUrl(),
						'pageTitle' => Str::unhtml($model->title()->toString()),
						'title' => $meta->metaTitle()->toString(),
						'description' => $meta->metaDescription()->toString(),
						'ogSiteName' => $meta->ogSiteName()->toString(),
						'ogTitle' => $meta->ogTitle()->toString(),
						'ogDescription' => $meta->ogDescription()->toString(),
						'ogImage' => $meta->ogImage(),
						'cropOgImage' => $meta->cropOgImage()->toBool(),
						'panelUrl' => method_exists($model, 'panel') ? "{$model->panel()->url()}?tab=seo" : null
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
				/** @var Section $this */
				$model = $this->model();
				if (!$model instanceof Page) {
					// only works for pages (not site, files, etc.)
					return [];
				}

				// In Kirby 5, use the changes version if it exists
				// clone the model with the content from the changes version
				$changesVersion = $model->version('changes');
				if ($changesVersion->exists('current')) {
					$model = $model->clone(['content' => $changesVersion->content()->toArray()]);
				}

				// Render the page
				// the encoding prefix makes libxml read the HTML as UTF-8
				$dom = new DOMDocument();
				$previous = libxml_use_internal_errors(true);
				$dom->loadHTML('<?xml encoding="UTF-8">' . $model->render(), LIBXML_NOERROR | LIBXML_NOWARNING);
				libxml_clear_errors();
				libxml_use_internal_errors($previous);

				$xpath = new DOMXPath($dom);
				/** @var \DOMNodeList<\DOMElement> $headings */
				$headings = $xpath->query('//h1|//h2|//h3|//h4|//h5|//h6');
				$data = [];

				foreach ($headings as $heading) {
					$data[] = [
						'level' => (int)str_replace('h', '', $heading->nodeName),
						'text' => $heading->textContent
					];
				}

				return $data;
			}
		]
	],
	'seo-license' => [
		'computed' => [
			'local' => fn () => App::instance()->system()->isLocal(),
			'state' => function () {
				/** @var License $license */
				$license = App::plugin('tobimori/seo')?->license();

				return $license->state();
			}
		]
	],
	'seo-search-console' => [
		'mixins' => ['headline'],
		'computed' => [
			'status' => function () {
				$gsc = Seo::option('components.gsc');
				return match (true) {
					!$gsc::hasCredentials() => 'NO_CREDENTIALS',
					!$gsc::isConnected() => 'NOT_CONNECTED',
					!$gsc::property() => 'SELECT_PROPERTY',
					default => 'CONNECTED'
				};
			},
			'property' => fn () => Seo::option('components.gsc')::property(),
			'pageUrl' => function () {
				/** @var Section $this */
				$model = $this->model();
				if ($model instanceof Page) {
					return '/' . $model->uri();
				}
				return null;
			},
			'data' => function () {
				/** @var Section $this */
				$gsc = Seo::option('components.gsc');
				if (!$gsc::ready()) {
					return [];
				}

				$metric = (string)App::instance()->request()->get('metric', 'clicks');
				$limit = (int)App::instance()->request()->get('limit', 10);
				$asc = in_array($metric, ['position', 'query'], true);

				try {
					return $gsc::queryForModel($this->model(), $metric, $limit, $asc);
				} catch (\Exception $e) {
					return [];
				}
			}
		]
	]
];
