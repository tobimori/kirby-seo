<?php

namespace tobimori\Seo\Views;

use Kirby\Content\VersionId;
use Kirby\Exception\NotFoundException;
use Kirby\Panel\Ui\Item\PageItem;
use Kirby\Toolkit\I18n;
use tobimori\Seo\Audit\Links\Checker;
use tobimori\Seo\Audit\Links\Report;

/**
 * Panel view listing all links of the rendered pages, one row per linked URL.
 * The table is read-only: links are fixed on the pages linking to them
 */
class LinksView extends OverviewView
{
	public const SORTABLE = ['url', 'status', 'pages'];
	// cheap values first, the titles of the linking pages need a lookup per page
	public const SEARCHABLE = ['url', 'details', 'pages'];

	/**
	 * Seconds per scan request of the Panel
	 */
	public const STEP = 4;

	protected Report|null $report = null;

	public function load(): array
	{
		return VersionId::render('changes', function () {
			['search' => $search, 'sort' => $sort, 'dir' => $dir, 'issue' => $issue] = $this->query(
				self::SORTABLE,
				[...Report::FILTERS, 'issues']
			);
			$scope = $this->kirby->request()->get('scope') === 'content' ? 'content' : null;

			$links = array_values($this->report()->links());

			if ($scope) {
				$links = array_values(array_filter($links, fn ($link) => $link['content'] !== ''));
			}

			// counts of the filters within the scope
			$summary = Report::summary($links);

			if ($issue) {
				$links = array_values(array_filter($links, fn ($link) => Report::has($link, $issue)));
			}

			$links = $this->search($links, $search, self::SEARCHABLE, fn ($link, $key) => match ($key) {
				'url' => $link['url'],
				'details' => $this->reason($link),
				// line breaks between the titles, so a search can't match across two of them
				'pages' => implode("\n", array_map($this->title(...), array_keys(Report::pages($link)))),
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
					'columns' => $this->columns(),
					'rows' => array_map($this->row(...), $visible),
					'pagination' => $pagination,
					'summary' => $summary,
					'issue' => $issue,
					'scope' => $scope,
					'search' => $search,
					'sort' => $sort,
					'dir' => $dir,
					'progress' => (new Checker())->progress(),
					'scanned' => !$this->report()->isEmpty(),
				]
			];
		});
	}

	/**
	 * Runs a step of the check (without queues) & returns the progress
	 */
	public function scan(): array
	{
		$checker = new Checker();

		return Checker::usesQueue() ? $checker->progress() : $checker->step(self::STEP);
	}

	/**
	 * Checks all pages & URLs again
	 */
	public function rescan(): array
	{
		$checker = new Checker();
		$checker->invalidate();
		Checker::dispatch(full: true, now: true);

		return $checker->progress();
	}

	/**
	 * Checks a link again: the pages linking to it & the URL itself
	 */
	public function recheck(): array
	{
		$checker = new Checker();
		$checker->recheck((string)$this->kirby->request()->get('url'));
		Checker::dispatch(now: true);

		return $checker->progress();
	}

	/**
	 * Drawer with all pages linking to the URL, links in the content first
	 */
	public function linkingPages(string $url): array
	{
		$link = $this->report()->links()[$url] ?? throw new NotFoundException(message: 'Link not found');
		[$pagination, $visible] = $this->paginate($this->linking($link));

		return [
			'component' => 'k-seo-link-drawer',
			'props' => [
				'icon' => 'url',
				'title' => $this->display($url),
				'url' => $url,
				'state' => $link['state'],
				'details' => $this->reason($link),
				'items' => array_map(fn ($entry) => [
					...(new PageItem(page: $entry['page']))->props(),
					'info' => I18n::translate("seo.overview.links.location.{$entry['location']}"),
				], $visible),
				'pagination' => $pagination,
			],
		];
	}

	/**
	 * Pages linking to the URL, links in the content first, then by title
	 *
	 * @return array<array{page: \Kirby\Cms\Page, location: string}>
	 */
	protected function linking(array $link): array
	{
		$entries = [];

		foreach (Report::pages($link) as $id => $location) {
			if ($page = $this->kirby->page($id)) {
				$entries[] = ['page' => $page, 'location' => $location];
			}
		}

		return $this->sort($entries, fn ($entry) => ($entry['location'] === 'content' ? '0' : '1') . $entry['page']->title()->value(), 'asc');
	}

	protected function report(): Report
	{
		return $this->report ??= new Report();
	}

	/**
	 * Internal links without the host, as all of them have the same
	 */
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

	protected function title(string $id): string
	{
		return (string)$this->kirby->page($id)?->title()->value();
	}

	/**
	 * Why the link is broken (or can't be checked), the target of redirects, …
	 * Empty for links that work
	 */
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
			'location' => [
				'hidden' => true,
				'label' => I18n::translate('seo.overview.links.columns.location'),
				'toggle' => I18n::translate('seo.overview.links.columns.location'),
				'type' => 'text',
				'width' => '8rem'
			],
		];
	}

	protected function row(array $link): array
	{
		// links in the content can be edited, links in the layout (navigation, footer) come from templates
		$location = match (true) {
			$link['content'] !== '' && $link['layout'] !== '' => 'both',
			$link['content'] !== '' => 'content',
			default => 'layout',
		};

		return [
			'id' => $link['url'],
			'status' => ['state' => $link['state'], 'code' => $link['code']],
			'url' => [
				'text' => $this->display($link['url']),
				'href' => $link['url'],
				'target' => '_blank',
			],
			'details' => $this->reason($link),
			// the first page (preferably linking in the content), all pages are listed in a drawer
			'pages' => [
				'text' => $this->title(array_key_first(Report::pages($link))),
				'total' => $link['total'],
			],
			'location' => I18n::translate("seo.overview.links.location.{$location}"),
		];
	}
}
