<?php

use Kirby\Cms\App;
use tobimori\Seo\Seo;

return function (App $kirby) {
	if (!Seo::option('robots.active') || !Seo::option('robots.pageSettings')) {
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

	$index = Seo::option('robots.index');
	foreach ($kirby->option('tobimori.seo.robots.types') as $robots) {
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
