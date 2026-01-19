<?php

use Kirby\Cms\App;
use Kirby\Cms\Find;
use Kirby\Cms\ModelWithContent;
use Kirby\Cms\Page;
use Kirby\Toolkit\I18n;
use tobimori\Seo\Buttons\RobotsViewButton;
use tobimori\Seo\Buttons\UtmShareViewButton;
use tobimori\Seo\Dialogs\UtmShareDialog;
use tobimori\Seo\Seo;

return [
	'seo' => fn () =>
	[
		'buttons' => [
			'page.robots' => fn (Page $page) => new RobotsViewButton($page),
			'utm-share' => fn (ModelWithContent $model) => new UtmShareViewButton($model)
		],
		'drawers' => [
			'gsc-data' => [
				'pattern' => 'seo/gsc/data/(:all)',
				'load' => function (string $parent) {
					$kirby = App::instance();
					$request = $kirby->request();
					$metric = $request->get('metric', 'clicks');
					$asc = (bool) $request->get('asc', in_array($metric, ['position', 'query']) ? 1 : 0);
					$page = max(1, (int) $request->get('page', 1));
					$limit = max(1, min(100, (int) $request->get('limit', 20)));

					try {
						$model = Find::parent(ltrim($parent, '/'));
					} catch (\Exception $e) {
						return ['component' => 'k-error-drawer', 'props' => ['message' => 'Model not found']];
					}

					$gsc = Seo::option('components.gsc');
					if (!$gsc::hasCredentials() || !$gsc::isConnected() || !$gsc::property()) {
						return ['component' => 'k-error-drawer', 'props' => ['message' => 'GSC not connected']];
					}

					$title = I18n::translate('seo.sections.searchConsole.title');
					if ($model instanceof Page) {
						$title .= ' · ' . $model->title()->value();
					}

					$data = $gsc::queryForModel($model, $metric, 25000, $asc);
					$total = count($data);
					$pageData = array_slice($data, ($page - 1) * $limit, $limit);

					// format numbers with locale
					$locale = $kirby->panelLanguage();
					$number = new NumberFormatter($locale, NumberFormatter::DECIMAL);
					$percent = new NumberFormatter($locale, NumberFormatter::PERCENT);
					$percent->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, 1);
					$percent->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, 1);
					$decimal = new NumberFormatter($locale, NumberFormatter::DECIMAL);
					$decimal->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, 1);
					$decimal->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, 1);

					$rows = array_map(fn ($row) => [
						'query' => $row['keys'][0],
						'clicks' => $number->format($row['clicks']),
						'impressions' => $number->format($row['impressions']),
						'ctr' => $percent->format($row['ctr']),
						'position' => $decimal->format($row['position'])
					], $pageData);

					return [
						'component' => 'k-gsc-drawer',
						'props' => [
							'title' => $title,
							'icon' => 'google',
							'parent' => $parent,
							'metric' => $metric,
							'sortAsc' => $asc,
							'page' => $page,
							'limit' => $limit,
							'total' => $total,
							'columns' => [
								'query' => ['label' => I18n::translate('seo.sections.searchConsole.query'), 'width' => '1/2', 'mobile' => true],
								'clicks' => ['label' => I18n::translate('seo.sections.searchConsole.clicks'), 'width' => '1/8', 'align' => 'right'],
								'impressions' => ['label' => I18n::translate('seo.sections.searchConsole.impressions'), 'width' => '1/8', 'align' => 'right'],
								'ctr' => ['label' => I18n::translate('seo.sections.searchConsole.ctr'), 'width' => '1/8', 'align' => 'right'],
								'position' => ['label' => I18n::translate('seo.sections.searchConsole.position'), 'width' => '1/8', 'align' => 'right', 'mobile' => true]
							],
							'rows' => $rows
						]
					];
				}
			]
		],
		'dialogs' => [
			'utm-share' => [
				'pattern' => 'seo/utm-share/(:all)',
				'controller' => UtmShareDialog::class
			],
			'gsc-select-property' => [
				'pattern' => 'seo/gsc/select-property',
				'load' => function () {
					$siteUrl = App::instance()->site()->url();
					$gsc = Seo::option('components.gsc');

					$properties = $gsc::listProperties();
					$options = array_map(fn ($p) => [
						'value' => $p['siteUrl'],
						'text' => str_starts_with($p['siteUrl'], 'sc-domain:')
							? substr($p['siteUrl'], 10) . ' (' . I18n::translate('seo.sections.searchConsole.scDomain') . ')'
							: $p['siteUrl']
					], $properties);

					$currentProperty = $gsc::property();
					$defaultProperty = $currentProperty ?? $gsc::findMatchingProperty($siteUrl);

					return [
						'component' => 'k-form-dialog',
						'props' => [
							'fields' => [
								'property' => [
									'label' => I18n::translate('seo.sections.searchConsole.selectPropertyLabel'),
									'type' => 'select',
									'required' => true,
									'options' => $options,
									'empty' => false
								]
							],
							'submitButton' => I18n::translate('select'),
							'value' => [
								'property' => $defaultProperty
							]
						]
					];
				},
				'submit' => function () {
					$property = App::instance()->request()->get('property');
					Seo::option('components.gsc')::setProperty($property);

					return [
						'event' => 'gsc.propertySelected'
					];
				}
			]
		]
	]
];
