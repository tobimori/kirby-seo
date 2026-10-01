<?php

use Kirby\Cms\App;
use tobimori\Seo\Views\OverviewView;

return [
	'routes' => [
		[
			// current state of the given rows (locks, values) without reloading the whole table
			'pattern' => 'seo/overview/rows',
			'method' => 'POST',
			'action' => fn () => (new OverviewView())->rows((array)App::instance()->request()->get('ids', []))
		],
		[
			// saves multiple pages to their changes versions in a single request
			'pattern' => 'seo/overview/save',
			'method' => 'POST',
			'action' => fn () => (new OverviewView())->save((array)App::instance()->request()->get('changes', []))
		],
	]
];
