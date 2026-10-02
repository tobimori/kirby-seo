<?php

namespace tobimori\Seo\Views;

use Kirby\Cms\ModelWithContent;
use Kirby\Content\Changes;
use Kirby\Content\VersionId;
use Kirby\Exception\NotFoundException;
use Kirby\Exception\PermissionException;
use Kirby\Panel\Ui\Item\PageItem;
use Kirby\Toolkit\I18n;
use Kirby\Toolkit\Str;
use tobimori\Seo\Links\Checker;
use tobimori\Seo\Links\Report;

/**
 * Panel view listing all links of the rendered pages, one row per linked URL.
 * The table is read-only: links are fixed on the pages linking to them
 */
class LinksView extends OverviewView
{
	public const SORTABLE = ['url', 'status', 'pages'];
	public const FILTERS = ['broken', 'anchor', 'redirect', 'unknown'];
	public const ISSUES = ['broken', 'anchor', 'redirect'];

	/**
	 * Seconds per scan request of the Panel
	 */
	public const STEP = 4;

	protected Report|null $report = null;

	public function load(): array
	{
		if (!static::canAccess()) {
			throw new PermissionException(key: 'access.view');
		}

		return VersionId::render('changes', function () {
			$request = $this->kirby->request();
			$search = trim($request->get('search', ''));
			$sort = in_array($request->get('sort'), self::SORTABLE, true) ? $request->get('sort') : null;
			$dir = $request->get('dir') === 'desc' ? 'desc' : 'asc';
			$issue = in_array($request->get('issue'), [...self::FILTERS, 'issues'], true) ? $request->get('issue') : null;
			$scope = $request->get('scope') === 'content' ? 'content' : null;

			$links = array_values($this->report()->links());

			if ($scope) {
				$links = array_values(array_filter($links, fn ($link) => in_array('content', $link['pages'], true)));
			}

			// counts of the filters within the scope
			$summary = $this->summary($links);

			if ($issue) {
				$links = array_values(array_filter($links, fn ($link) => $issue === 'issues'
					? in_array($link['state'], self::ISSUES, true)
					: $link['state'] === $issue));
			}

			if ($search !== '') {
				$links = array_values(array_filter($links, function ($link) use ($search) {
					foreach ([$link['url'], $this->reason($link), ...array_map($this->title(...), array_keys($link['pages']))] as $value) {
						if (Str::contains($value, $search, true)) {
							return true;
						}
					}

					return false;
				}));
			}

			$links = $this->sort($links, fn ($link) => match ($sort) {
				'pages' => (string)count($link['pages']),
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
		if (!static::canAccess()) {
			throw new PermissionException(key: 'access.view');
		}

		$checker = new Checker();

		return Checker::usesQueue() ? $checker->progress() : $checker->step(self::STEP);
	}

	/**
	 * Checks all pages & URLs again
	 */
	public function rescan(): array
	{
		if (!static::canAccess()) {
			throw new PermissionException(key: 'access.view');
		}

		$checker = new Checker();
		$checker->invalidate();
		Checker::dispatch(full: true);

		return $checker->progress();
	}

	/**
	 * Drawer with all pages linking to the URL, links in the content first
	 */
	public function linkingPages(string $url): array
	{
		if (!static::canAccess()) {
			throw new PermissionException(key: 'access.view');
		}

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

		foreach ($link['pages'] as $id => $location) {
			if ($page = $this->kirby->page($id)) {
				$entries[] = ['page' => $page, 'location' => $location];
			}
		}

		return $this->sort($entries, fn ($entry) => ($entry['location'] === 'content' ? '0' : '1') . $entry['page']->title()->value(), 'asc');
	}

	/**
	 * Checks a link again: the pages linking to it & the URL itself
	 */
	public function recheck(): array
	{
		if (!static::canAccess()) {
			throw new PermissionException(key: 'access.view');
		}

		$checker = new Checker();
		$checker->recheck((string)$this->kirby->request()->get('url'));
		Checker::dispatch();

		return $checker->progress();
	}

	protected function report(): Report
	{
		return $this->report ??= new Report();
	}

	/**
	 * Number of links per state
	 */
	protected function summary(array $links): array
	{
		$summary = array_fill_keys(self::FILTERS, 0);

		foreach ($links as $link) {
			if (isset($summary[$link['state']])) {
				$summary[$link['state']]++;
			}
		}

		return $summary;
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
		$locations = array_values(array_unique($link['pages']));
		$location = count($locations) > 1 ? 'both' : $locations[0];

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
				'text' => $this->title(array_key_first(array_filter($link['pages'], fn ($location) => $location === 'content')) ?? array_key_first($link['pages'])),
				'total' => count($link['pages']),
			],
			'location' => I18n::translate("seo.overview.links.location.{$location}"),
		];
	}

	// the table is read-only, there's nothing to edit, save or publish

	protected function find(string $id): array|null
	{
		return null;
	}

	protected function ids(ModelWithContent $model): array
	{
		return [];
	}

	protected function tracked(Changes $changes): iterable
	{
		return [];
	}

	protected function input(array $entry, array $columns): array
	{
		return [];
	}

	protected function live(): array
	{
		return [];
	}
}
