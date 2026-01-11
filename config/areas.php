<?php

use Kirby\Cms\ModelWithContent;
use Kirby\Cms\Page;
use tobimori\Seo\Buttons\RobotsViewButton;
use tobimori\Seo\Buttons\UtmShareViewButton;
use tobimori\Seo\Dialogs\UtmShareDialog;

return [
	'seo' => fn () =>
	[
		'buttons' => [
			'page.robots' => fn (Page $page) => new RobotsViewButton($page),
			'utm-share' => fn (ModelWithContent $model) => new UtmShareViewButton($model)
		],
		'dialogs' => [
			'utm-share' => [
				'pattern' => 'seo/utm-share/(:all)',
				'controller' => UtmShareDialog::class
			]
		]
	]
];
