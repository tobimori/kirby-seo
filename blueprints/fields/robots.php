<?php

use Kirby\Cms\App;
use Kirby\Toolkit\A;
use Kirby\Toolkit\Str;
use tobimori\Seo\Meta;

return function (App $kirby) {
	if (
		!$kirby->option('tobimori.seo.robots.pageSettings', $kirby->option('tobimori.seo.robots.active', false))
		|| !($page = Meta::currentPage())
	) {
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
		$upper = Str::ucfirst($robots);
		$default = $page->metadata()->get("robots{$upper}", ['fields'])->toBool() ? t('yes') : t('no');

		$fields["robots{$upper}"] = [
			'label' =>  'robots-' . $robots,
			'type' => 'toggles',
			'help' => 'robots-' . $robots . '-help',
			'width' => '1/2',
			'default' => 'default',
			'required' => true,
			'options' => [
				'default' => A::join([t('default-select'), $default], ' '),
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
