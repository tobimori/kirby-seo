<?php

/**
 * Social Media Accounts field
 * Allows social media account list to be filled by config options
 */

use Kirby\Cms\App;

return function (App $kirby) {
	$fields = [];

	foreach ($kirby->option('tobimori.seo.socialMedia') as $key => $value) {
		if ($value) {
			$fields[$key] = [
				'label' => ucfirst($key),
				'type' => 'url',
				'icon' => strtolower($key),
				'placeholder' => $value
			];
		}
	}

	return [
		'label' => 'social-media-accounts',
		'type' => 'object',
		'help' => 'social-media-accounts-help',
		'fields' => $fields
	];
};
