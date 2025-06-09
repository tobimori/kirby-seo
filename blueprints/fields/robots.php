<?php

use Kirby\Cms\App;
use Kirby\Toolkit\A;
use Kirby\Toolkit\Str;
use tobimori\Seo\Meta;
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

	$page = Meta::currentPage();
	foreach ($kirby->option('tobimori.seo.robots.types') as $robots) {
		$upper = Str::ucfirst($robots);

		$fields["robots{$upper}"] = [
			'label' =>  "seo.fields.robots.{$robots}.label",
			'type' => 'toggles',
			'help' => "seo.fields.robots.{$robots}.help",
			'width' => '1/2',
			'default' => 'default',
			'reset' => false,
			'options' => [
				'default' => $page ?
					A::join([
						t('seo.common.default'),
						$page->metadata()->get("robots{$upper}", ['fields'])->toBool() ? t('seo.common.yes') : t('seo.common.no')
					], ' ')
					: t('seo.common.default'),
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
