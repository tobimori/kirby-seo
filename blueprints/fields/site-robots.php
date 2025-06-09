<?php

use Kirby\Cms\App;

return function (App $kirby) {
	if (!$kirby->option('tobimori.seo.robots.active') || !$kirby->option('tobimori.seo.robots.pageSettings')) {
		return [
			'type' => 'hidden'
		];
	}

	$fields = [
		'_robotsHeadline' => [
			'label' => 'seo.fields.robots.label',
			'type' => 'headline',
			'numbered' => false,
		]
	];

	foreach ($kirby->option('tobimori.seo.robots.types') as $robots) {
		$index = $kirby->option('tobimori.seo.robots.index');
		if (is_callable($index)) {
			$index = $index();
		}

		$fields["robots{$robots}"] = [
			'label' =>  "seo.fields.robots.{$robots}.label",
			'type' => 'toggles',
			'help' => "seo.fields.robots.{$robots}.help",
			'width' => '1/2',
			'default' => 'default',
			'reset' => false,
			'options' => [
				'default' => t('seo.common.default') . ' ' . ($index ? t('seo.common.yes') : t('seo.common.no')),
				'true' => t('seo.common.yes'),
				'false' => t('seo.common.no'),
			]
		];
	}

	$fields['_seoLine3'] = [
		'type' => 'line'
	];

	return [
		'type' => 'group',
		'fields' => $fields,
	];
};
