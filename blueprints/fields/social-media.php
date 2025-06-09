<?php

/**
 * Social Media Accounts field
 * Allows social media account list to be filled by config options
 */

use tobimori\Seo\Seo;

return function () {
	$fields = [];

	foreach (Seo::option('socialMedia') as $key => $value) {
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
		'label' => 'seo.fields.socialMediaAccounts.label',
		'type' => 'object',
		'help' => 'seo.fields.socialMediaAccounts.help',
		'fields' => $fields
	];
};
