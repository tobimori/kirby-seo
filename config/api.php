<?php

use Kirby\Cms\App;
use tobimori\Seo\Views\ImagesView;
use tobimori\Seo\Views\LinksView;
use tobimori\Seo\Views\EditableOverviewView;

return [
	'routes' => [
		[
			'pattern' => 'seo/overview/(pages|images)/(rows|save|publish|discard)',
			'method' => 'POST',
			'action' => function (string $tab, string $action) {
				$request = App::instance()->request();
				$view = EditableOverviewView::for($tab);

				return match ($action) {
					'save' => $view->save((array)$request->get('changes', [])),
					default => $view->$action((array)$request->get('ids', [])),
				};
			}
		],
		[
			'pattern' => 'seo/overview/images/generate',
			'method' => 'POST',
			'action' => fn () => (new ImagesView())->generate((string)App::instance()->request()->body()->get('id'))
		],
		[
			'pattern' => 'seo/overview/links/(scan|rescan|recheck)',
			'method' => 'POST',
			'action' => fn (string $action) => (new LinksView())->$action()
		],
	]
];
