<?php

use Kirby\Cms\App;
use tobimori\Seo\Views\OverviewView;

return [
	'routes' => [
		[
			// - rows: current state of the given rows (locks, values) without reloading the whole table
			// - save: saves multiple rows to the changes versions of their models in a single request
			// - publish/discard: the unsaved changes of the given rows
			'pattern' => 'seo/overview/(pages)/(rows|save|publish|discard)',
			'method' => 'POST',
			'action' => function (string $tab, string $action) {
				$request = App::instance()->request();
				$view = OverviewView::for($tab);

				return match ($action) {
					'save' => $view->save((array)$request->get('changes', [])),
					default => $view->$action((array)$request->get('ids', [])),
				};
			}
		],
	]
];
