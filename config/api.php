<?php

use Kirby\Cms\App;
use Kirby\Toolkit\A;
use tobimori\Seo\Views\EditableOverviewView;
use tobimori\Seo\Views\ImagesView;
use tobimori\Seo\Views\LinksView;

return [
	'routes' => [
		[
			'pattern' => 'seo/overview/(pages|images)/(rows|save|publish|discard)',
			'method' => 'POST',
			'action' => /** @param 'rows'|'save'|'publish'|'discard' $action */ function (string $tab, string $action) {
				$request = App::instance()->request();
				$view = EditableOverviewView::for($tab);

				return match ($action) {
					'save' => $view->save(A::wrap($request->get('changes', []))),
					'rows' => $view->rows(A::wrap($request->get('ids', []))),
					'publish' => $view->publish(A::wrap($request->get('ids', []))),
					'discard' => $view->discard(A::wrap($request->get('ids', [])))
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
			'action' => /** @param 'scan'|'rescan'|'recheck' $action */ fn (string $action) => match ($action) {
				'scan' => (new LinksView())->scan(),
				'rescan' => (new LinksView())->rescan(),
				'recheck' => (new LinksView())->recheck()
			}
		]
	]
];
