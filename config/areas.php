<?php

use tobimori\Seo\RobotsViewButton;

return [
	'seo' => fn () =>
	[
		'buttons' => [
			'robots' => function ($page = null) {
				// Only show on page views
				if (!$page) {
					return false;
				}

				// Return a new instance of the button
				return new RobotsViewButton($page);
			}
		]
	]
];
