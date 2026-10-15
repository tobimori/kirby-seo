<?php

use Kirby\Cms\ModelWithContent;
use Kirby\Cms\Page;
use Kirby\Cms\Site;
use Kirby\Panel\Panel;
use Kirby\Toolkit\I18n;
use tobimori\Seo\Buttons\OverviewLanguagesButton;
use tobimori\Seo\Buttons\RobotsViewButton;
use tobimori\Seo\Buttons\UtmShareViewButton;
use tobimori\Seo\Dialogs\LicenseDialog;
use tobimori\Seo\Dialogs\SearchConsolePropertyDialog;
use tobimori\Seo\Dialogs\UtmShareDialog;
use tobimori\Seo\Drawers\SearchConsoleDrawer;
use tobimori\Seo\Seo;
use tobimori\Seo\Views\ImagesView;
use tobimori\Seo\Views\LinksView;
use tobimori\Seo\Views\OverviewView;
use tobimori\Seo\Views\PagesView;

return [
	'seo' => fn () => [
		'label' => I18n::translate('seo.overview.title'),
		'icon' => 'search',
		'link' => 'seo',
		'menu' => fn () => OverviewView::canAccess(),
		'views' => [
			'seo' => [
				'pattern' => 'seo',
				'action' => fn () => Panel::go('seo/pages')
			],
			'seo.pages' => [
				'pattern' => 'seo/pages',
				'action' => PagesView::class
			],
			'seo.images' => [
				'pattern' => 'seo/images',
				'action' => ImagesView::class
			],
			'seo.links' => [
				'pattern' => 'seo/links',
				'action' => LinksView::class
			]
		],
		'buttons' => [
			'page.robots' => fn (Page $page) => Seo::option('robots.enabled') ? new RobotsViewButton($page) : null,
			'utm-share' => fn (ModelWithContent $model) => new UtmShareViewButton($model),
			'seo.overview.languages' => fn (Site $site) => new OverviewLanguagesButton($site)
		],
		'drawers' => [
			'gsc-data' => [
				'pattern' => 'seo/gsc/data/(:all)',
				'action' => SearchConsoleDrawer::class
			]
		],
		'dialogs' => [
			'activate' => [
				'pattern' => 'seo/activate',
				'action' => LicenseDialog::class
			],
			'utm-share' => [
				'pattern' => 'seo/utm-share/(:all)',
				'action' => UtmShareDialog::class
			],
			'gsc-select-property' => [
				'pattern' => 'seo/gsc/select-property',
				'action' => SearchConsolePropertyDialog::class
			]
		]
	]
];
