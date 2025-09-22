<?php

use Kirby\Cms\App;
use Kirby\Toolkit\Str;

return function (App $kirby) {
	$blueprint = [
		'type' => 'files',
		'multiple' => false,
		'uploads' => [],
		'query' => 'model.images'
	];

	if ($parent = option('tobimori.seo.files.parent')) {
		$blueprint['uploads'] = [
			'parent' => $parent
		];
		$blueprint['query'] = "{$parent}.images";
	}

	if ($template = option('tobimori.seo.files.template')) {
		$blueprint['uploads'] = [
			...$blueprint['uploads'],
			'template' => $template
		];

		$blueprint['query'] = "{$blueprint['query']}.filterBy('template', '{$template}')";
	}

	return $blueprint;
};
