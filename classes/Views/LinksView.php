<?php

namespace tobimori\Seo\Views;

use Kirby\Content\VersionId;
use Kirby\Toolkit\I18n;
use tobimori\Seo\Audit\Links\Checker;
use tobimori\Seo\Audit\Links\Index;
use tobimori\Seo\Audit\Links\Report;

class LinksView extends OverviewView
{
	public const SORTABLE = ['url', 'status', 'pages'];
	// cheap values first, the titles of the linking pages need a lookup per page
	public const SEARCHABLE = ['url', 'details', 'pages'];

	/**
	 * Time budget in seconds for a Panel-driven scan step
	 */
	public const STEP = 4;

	/**
	 * Maximum number of linking pages shown in a row's popover
	 */
	public const PAGES = 10;

	protected Report|null $report = null;

	public function load(): array
	{
		return VersionId::render('changes', function () {
			$query = $this->query(
				self::SORTABLE,
				[...Report::FILTERS, 'issues']
			);
			['search' => $search, 'sort' => $sort, 'dir' => $dir, 'issue' => $issue] = $query;
			$links = $this->report()->links();

			$summary = Report::summary($links);

			if ($issue) {
				$links = array_filter($links, fn ($link) => Report::has($link, $issue));
			}

			$links = $this->search($links, $search, self::SEARCHABLE, fn ($link, $key) => match ($key) {
				'url' => $link['url'],
				'details' => $this->reason($link),
				// line breaks between the titles, so a search can't match across two of them
				'pages' => implode("\n", array_map($this->title(...), explode("\n", $link['pages']))),
			});

			$links = $this->sort($links, fn ($link) => match ($sort) {
				'pages' => (string)$link['total'],
				'url' => $this->display($link['url']),
				// the status & the default order: most severe first
				default => array_search($link['state'], Report::STATES, true) . $this->display($link['url']),
			}, $dir);

			[$pagination, $visible] = $this->paginate($links);

			return [
				'component' => 'k-seo-links-view',
				'title' => I18n::translate('seo.overview.title'),
				'props' => [
					...$this->layout('links'),
					...$query,
					'columns' => $this->columns(),
					'rows' => array_map($this->row(...), $visible),
					'pagination' => $pagination,
					'severity' => Report::SEVERITY,
					'summary' => $summary,
					'progress' => (new Checker())->progress(),
					'scanned' => !$this->report()->isEmpty(),
				]
			];
		});
	}

	/**
	 * Returns scan progress, running a step only when queue processing is disabled.
	 * With queues, enqueues a scan if results are incomplete and no scan is running,
	 * e.g. before the first scan. At most every 5 minutes, so failing jobs don't fill the queue
	 */
	public function scan(): array
	{
		$checker = new Checker();

		if (!Checker::usesQueue()) {
			return $checker->step(self::STEP);
		}

		$progress = $checker->progress();

		if (!$progress['done'] && !$progress['running'] && Index::cache()->get('links/dispatched') === null) {
			Checker::dispatch(now: true);
			Index::cache()->set('links/dispatched', time(), 5);
		}

		return $progress;
	}

	/**
	 * Invalidates all scan results and enqueues a full scan if queue processing is enabled.
	 * Otherwise, subsequent Panel scan requests perform the checks
	 */
	public function rescan(): array
	{
		$checker = new Checker();
		$checker->invalidate();
		Checker::dispatch(full: true, now: true);

		return $checker->progress();
	}

	/**
	 * Invalidates the link's source pages and external check result.
	 * Enqueues a scan if queue processing is enabled; otherwise the Panel continues it
	 */
	public function recheck(): array
	{
		$checker = new Checker();
		$checker->recheck((string)$this->kirby->request()->get('url'));
		Checker::dispatch(now: true);

		return $checker->progress();
	}

	protected function report(): Report
	{
		return $this->report ??= new Report();
	}

	protected function display(string $url): string
	{
		if (!Report::isInternal($url)) {
			return $url;
		}

		$parts = parse_url($url);

		return ($parts['path'] ?? '/')
			. (isset($parts['query']) ? "?{$parts['query']}" : '')
			. (isset($parts['fragment']) ? "#{$parts['fragment']}" : '');
	}

	protected function linking(array $pages): array
	{
		$items = [];

		foreach ($pages as $id) {
			if ($page = $this->kirby->page($id)) {
				$items[] = [
					'text' => (string)$page->title()->value(),
					'link' => $page->panel()->url(true),
				];
			}
		}

		return $items;
	}

	protected function title(string $id): string
	{
		return (string)$this->kirby->page($id)?->title()->value();
	}

	protected function reason(array $link): string
	{
		if ($link['reason'] === null) {
			return '';
		}

		return I18n::template("seo.overview.links.reason.{$link['reason']}", replace: [
			'code' => $link['code'],
			'target' => $link['target'] ? $this->display($link['target']) : null,
		]);
	}

	protected function columns(): array
	{
		return [
			'status' => [
				'label' => I18n::translate('seo.overview.links.columns.status'),
				'mobile' => true,
				'sortable' => true,
				'toggle' => I18n::translate('seo.overview.links.columns.status'),
				'type' => 'seo-link-status',
				'width' => '7rem'
			],
			'url' => [
				'label' => I18n::translate('seo.overview.links.columns.url'),
				'mobile' => true,
				'sortable' => true,
				'type' => 'seo-page',
			],
			'details' => [
				'label' => I18n::translate('seo.overview.links.columns.details'),
				'toggle' => I18n::translate('seo.overview.links.columns.details'),
				'type' => 'text',
				'width' => '1/4'
			],
			'pages' => [
				'label' => I18n::translate('seo.overview.links.columns.pages'),
				'mobile' => true,
				'sortable' => true,
				'toggle' => I18n::translate('seo.overview.links.columns.pages'),
				'type' => 'seo-link-pages',
				'width' => '1/4'
			],
		];
	}

	protected function row(array $link): array
	{
		$pages = explode("\n", $link['pages']);

		return [
			'id' => $link['url'],
			'status' => ['state' => $link['state'], 'code' => $link['code']],
			'url' => [
				'text' => $this->display($link['url']),
				'href' => $link['url'],
				'target' => '_blank',
			],
			'details' => $this->reason($link),
			'pages' => [
				'text' => $this->title($pages[0]),
				'total' => $link['total'],
				'items' => $this->linking(array_slice($pages, 0, self::PAGES)),
			],
		];
	}
}
