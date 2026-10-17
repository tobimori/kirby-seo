<?php

use Kirby\Cms\App;
use Kirby\Toolkit\I18n;
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
			'numbered' => false
		]
	];

	$index = Seo::option('robots.index');
	foreach (Seo::option('robots.types') as $robots) {
		$fields["robots{$robots}"] = [
			'label' => "seo.fields.robots.{$robots}.label",
			'type' => 'toggles',
			'help' => "seo.fields.robots.{$robots}.help",
			'width' => '1/2',
			'default' => 'default',
			'reset' => false,
			'options' => [
				'default' => I18n::template('seo.common.default') . ' ' . I18n::template($index ? 'seo.common.yes' : 'seo.common.no'),
				'true' => I18n::translate('seo.common.yes'),
				'false' => I18n::translate('seo.common.no')
			]
		];
	}

	$fields['_seoLine3'] = [
		'type' => 'line'
	];

	return [
		'type' => 'group',
		'fields' => $fields
	];
};
