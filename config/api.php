<?php

use Kirby\Cms\App;
use tobimori\Seo\Views\ImagesView;
use tobimori\Seo\Views\LinksView;
use tobimori\Seo\Views\OverviewView;

return [
	'routes' => [
		[
			// - rows: current state of the given rows (locks, values) without reloading the whole table
			// - save: saves multiple rows to the changes versions of their models in a single request
			// - publish/discard: the unsaved changes of the given rows
			'pattern' => 'seo/overview/(pages|images)/(rows|save|publish|discard)',
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
		[
			// streams an AI-generated alt text for an image row (server-sent events)
			'pattern' => 'seo/overview/images/generate',
			'method' => 'POST',
			'action' => function () {
				$kirby = App::instance();

				// the alt text is written in the language of the edited content
				if ($language = $kirby->api()->language()) {
					$kirby->setCurrentLanguage($language);
				}

				return (new ImagesView())->generate((string)$kirby->request()->body()->get('id'));
			}
		],
		[
			// - scan: runs a step of the link check (without queues) & returns the progress
			// - rescan: checks all pages & URLs again
			// - recheck: checks a link again (the pages linking to it & the URL)
			'pattern' => 'seo/overview/links/(scan|rescan|recheck)',
			'method' => 'POST',
			'action' => fn (string $action) => (new LinksView())->$action()
		],
	]
];
