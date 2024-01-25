<?php

use Kirby\Toolkit\A;

return [
	'seo-preview' => [
		'mixins' => ['headline'],
		'computed' => [
			'options' => function () {
				return A::map(option('tobimori.seo.previews'), fn ($item) => [
					'value' => $item,
					'text' => t($item)
				]);
			}
		]
	],
	'heading-structure' => [
		'mixins' => ['headline']
	]
];
