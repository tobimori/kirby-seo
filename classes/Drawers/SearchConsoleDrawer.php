<?php

namespace tobimori\Seo\Drawers;

use Kirby\Cms\Find;
use Kirby\Cms\ModelWithContent;
use Kirby\Cms\Page;
use Kirby\Exception\LogicException;
use Kirby\Panel\Controller\DrawerController;
use Kirby\Panel\Ui\Drawer;
use NumberFormatter;
use tobimori\Seo\GoogleSearchConsole;
use tobimori\Seo\Seo;

/**
 * All Search Console queries of a page or the site, sortable and paginated
 */
class SearchConsoleDrawer extends DrawerController
{
	public function __construct(
		protected ModelWithContent $model
	) {
		parent::__construct();
	}

	public static function factory(string $path): self
	{
		return new self(Find::parent($path));
	}

	public function load(): Drawer
	{
		/** @var class-string<GoogleSearchConsole> $gsc */
		$gsc = Seo::option('components.gsc');

		if (!$gsc::ready()) {
			throw new LogicException(message: 'GSC not connected');
		}

		$metric = (string)$this->request->get('metric', 'clicks');
		$asc = $this->request->get('asc', in_array($metric, ['position', 'query'], true) ? '1' : '0') === '1';
		$page = max(1, (int)$this->request->get('page', 1));
		$limit = max(1, min(100, (int)$this->request->get('limit', 20)));

		$title = $this->i18n('seo.sections.searchConsole.title');
		if ($this->model instanceof Page) {
			$title .= ' · ' . $this->model->title()->toString();
		}

		$data = $gsc::queryForModel($this->model, $metric, 25000, $asc);

		$locale = $this->kirby->panelLanguage();
		$number = new NumberFormatter($locale, NumberFormatter::DECIMAL);
		$percent = new NumberFormatter($locale, NumberFormatter::PERCENT);
		$percent->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, 1);
		$percent->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, 1);
		$decimal = new NumberFormatter($locale, NumberFormatter::DECIMAL);
		$decimal->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, 1);
		$decimal->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, 1);

		return new Drawer(
			component: 'k-gsc-drawer',
			icon: 'google',
			title: $title,
			metric: $metric,
			sortAsc: $asc,
			page: $page,
			limit: $limit,
			total: count($data),
			columns: [
				'query' => ['label' => $this->i18n('seo.sections.searchConsole.query'), 'width' => '1/2', 'mobile' => true],
				'clicks' => ['label' => $this->i18n('seo.sections.searchConsole.clicks'), 'width' => '1/8', 'align' => 'right'],
				'impressions' => ['label' => $this->i18n('seo.sections.searchConsole.impressions'), 'width' => '1/8', 'align' => 'right'],
				'ctr' => ['label' => $this->i18n('seo.sections.searchConsole.ctr'), 'width' => '1/8', 'align' => 'right'],
				'position' => ['label' => $this->i18n('seo.sections.searchConsole.position'), 'width' => '1/8', 'align' => 'right', 'mobile' => true]
			],
			rows: array_map(
				static fn (array $row) => [
					'query' => $row['keys'][0],
					'clicks' => $number->format($row['clicks']),
					'impressions' => $number->format($row['impressions']),
					'ctr' => $percent->format($row['ctr']),
					'position' => $decimal->format($row['position'])
				],
				array_slice($data, ($page - 1) * $limit, $limit)
			)
		);
	}
}
