<?php

use Kirby\Cms\App;
use Kirby\Toolkit\A;
use Kirby\Toolkit\Str;
use tobimori\Seo\Meta;

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

	$page = Meta::currentPage();
	foreach ($kirby->option('tobimori.seo.robots.types') as $robots) {
		$upper = Str::ucfirst($robots);

		$fields["robots{$upper}"] = [
			'label' =>  "robots-{$robots}",
			'type' => 'toggles',
			'help' => "robots-{$robots}-help",
			'width' => '1/2',
			'default' => 'default',
			'reset' => false,
			'options' => [
				'default' => $page ?
					A::join([
						t('default-select'),
						$page->metadata()->get("robots{$upper}", ['fields'])->toBool() ? t('yes') : t('no')
					], ' ')
					: t('default-select'),
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
