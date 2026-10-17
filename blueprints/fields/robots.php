<?php

use Kirby\Cms\App;
use Kirby\Toolkit\A;
use Kirby\Toolkit\I18n;
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
			'numbered' => false
		]
	];

	$page = Meta::currentPage();
	foreach (Seo::option('robots.types') as $robots) {
		$upper = Str::ucfirst($robots);

		$fields["robots{$upper}"] = [
			'label' => "seo.fields.robots.{$robots}.label",
			'type' => 'toggles',
			'help' => "seo.fields.robots.{$robots}.help",
			'width' => '1/2',
			'default' => 'default',
			'reset' => false,
			'options' => [
				'default' => $page
					? A::join([
						I18n::translate('seo.common.default'),
						$page->metadata()->get("robots{$upper}", ['fields'])->toBool() ? I18n::translate('seo.common.yes') : I18n::translate('seo.common.no')
					], ' ')
					: I18n::translate('seo.common.default'),
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
