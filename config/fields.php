<?php

use tobimori\Seo\Ai;

return [
	'seo-writer' => [
		'extends' => 'writer',
		'props' => [
			/**
			 * Enables/disables the character counter in the top right corner
			 */
			'ai' => function (string|bool $ai = false) {
				if (!Ai::enabled()) {
					return false;
				}

				return $ai;
			},

			// reset defaults
			'counter' => fn (bool $counter = false) => $counter, // we have to disable the counter because its at the same place as our ai button
			'inline' => fn (bool $inline = true) => $inline,
			'marks' => fn (array|bool|null $marks = false) => $marks,
			'nodes' => fn (array|bool|null $nodes = false) => $nodes,
		],
		'api' => fn () => [
			[
				'pattern' => '/ai',
				'action' => function () {
					$field = $this->field();
					ray($field);

					return [];
				}
			]
		]
	]
];
