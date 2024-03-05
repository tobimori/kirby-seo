<?php

use Kirby\Cms\App;
use Kirby\Toolkit\A;
use Kirby\Toolkit\Str;

return function (App $kirby) {
	if (!$kirby->option('tobimori.seo.robots.pageSettings', $kirby->option('tobimori.seo.robots.active', false))) {
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


	$path = App::instance()->request()->url()->toString();
	$matches = Str::match($path, "/pages\/([a-zA-Z0-9+]+)\/?/m");
	$page = App::instance()->site()->findPageOrDraft(Str::replace($matches[1], '+', '/'));

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
