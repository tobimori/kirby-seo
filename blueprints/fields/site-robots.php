<?php

use Kirby\Cms\App;

return function (App $kirby) {
	if (!$kirby->option('tobimori.seo.robots.active') || !$kirby->option('tobimori.seo.robots.pageSettings')) {
		return [
			'type' => 'hidden'
		];
	}

	$fields = [
		'robotsHeadline' => [
			'label' => 'robots',
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
			'label' =>  "robots-{$robots}",
			'type' => 'toggles',
			'help' => "robots-{$robots}-help",
			'width' => '1/2',
			'default' => 'default',
			'reset' => false,
			'options' => [
				'default' => t('default-select') . ' ' . ($index ? t('yes') : t('no')),
				'true' => t('yes'),
				'false' => t('no'),
			]
		];
	}

	$fields['seoLine3'] = [
		'type' => 'line'
	];

	return [
		'type' => 'group',
		'fields' => $fields,
	];
};
