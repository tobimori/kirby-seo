<?php

namespace tobimori\Seo\Views;

use Closure;
use Collator;
use Kirby\Cms\App;
use Kirby\Cms\Blueprint;
use Kirby\Cms\File;
use Kirby\Cms\Page;
use Kirby\Cms\Pages;
use Kirby\Exception\PermissionException;
use Kirby\Panel\Ui\Buttons\ViewButtons;
use Kirby\Toolkit\I18n;
use Kirby\Toolkit\Str;
use tobimori\Seo\Audit;
use tobimori\Seo\Audit\Links\Report;
use tobimori\Seo\Meta;
use tobimori\Seo\Seo;

/**
 * Base of the tabs of the SEO area: the header, stats & tabs shared by all of them,
 * and the sorting & pagination of their tables. Tabs that edit their rows extend `EditableOverviewView`
 */
abstract class OverviewView
{
	public const LIMIT = 50;
	// tabs & their icons
	public const TABS = ['pages' => 'page', 'images' => 'image', 'links' => 'url'];

	protected App $kirby;

	/**
	 * Meta instances per page, for the current request
	 */
	protected array $metas = [];

	/**
	 * Blueprints per template, for the current request
	 */
	protected array $blueprints = [];
	protected Audit\Pages|null $audit = null;
	protected Pages|null $pages = null;
	protected Audit\Images|null $images = null;

	/**
	 * All tabs, actions & drawers of the SEO area need the permission
	 */
	public function __construct()
	{
		if (!static::canAccess()) {
			throw new PermissionException(key: 'access.view');
		}

		$this->kirby = App::instance();
	}

	/**
	 * Whether the current user may access the overview
	 */
	public static function canAccess(): bool
	{
		return App::instance()->user()?->role()->permissions()->for('tobimori.seo', 'overview') === true;
	}

	/**
	 * Props of the view
	 */
	abstract public function load(): array;

	/**
	 * Props shared by all tabs: header buttons, stats & tabs
	 */
	protected function layout(string $tab): array
	{
		return [
			// `panel.content` expects the content props of a model view, e.g. the languages dropdown
			// (and plugins building on it) unlocks the content via `{api}/changes/unlock` before
			// switching. The view buttons are bound to the site, so is this; the overview itself
			// has no content: edits are saved per model & their locks are released by the view
			'api' => 'site',
			'lock' => ['isLocked' => false],
			'versions' => ['latest' => [], 'changes' => []],
			// configurable via `panel.viewButtons.seo.overview`, like the views of Kirby itself
			'buttons' => fn () => ViewButtons::view('seo.overview', model: $this->kirby->site())
				->defaults('languages')
				->render(),
			'stats' => [
				...$this->audit()->stats(),
				'images' => $this->images()->stats(),
				'links' => (new Report())->stats(),
			],
			'tab' => $tab,
			'tabs' => array_map(fn ($name, $icon) => [
				'name' => $name,
				'label' => I18n::translate("seo.overview.tabs.{$name}"),
				'icon' => $icon,
				'link' => "seo/{$name}",
			], array_keys(self::TABS), self::TABS),
		];
	}

	/**
	 * All pages with SEO fields the current user is allowed to see in the Panel
	 */
	protected function pages(): Pages
	{
		return $this->pages ??= $this->kirby->site()->index(true)->filter($this->isListedPage(...));
	}

	/**
	 * Whether the page is part of the overview
	 * (skips e.g. form submissions or other pages that aren't meant to be public)
	 */
	protected function isListedPage(Page $page): bool
	{
		return $page->isListable()
			&& $this->blueprint($page)->field('metaTitle') !== null
			&& $this->blueprint($page)->field('metaDescription') !== null;
	}

	/**
	 * Blueprint of the model's template: each model creates & keeps its own blueprint object,
	 * the blueprints of all pages & images of larger sites wouldn't fit into memory
	 */
	protected function blueprint(Page|File $model): Blueprint
	{
		$key = $model instanceof Page
			? 'pages/' . $model->intendedTemplate()->name()
			: 'files/' . ($model->template() ?? 'default');

		return $this->blueprints[$key] ??= $model->blueprint();
	}

	/**
	 * Checks of all pages (e.g. for duplicates), shared by all rows
	 */
	protected function audit(): Audit\Pages
	{
		return $this->audit ??= new Audit\Pages($this->pages());
	}

	/**
	 * Not using $page->metadata(), as page models of other plugins might define their own method with that name
	 */
	protected function meta(Page $page): Meta
	{
		return $this->metas[$page->id()] ??= new (Seo::option('components.meta'))($page);
	}

	/**
	 * Alt texts of all images, shared by the images tab & the stats
	 */
	protected function images(): Audit\Images
	{
		return $this->images ??= new Audit\Images();
	}

	/**
	 * Locale-aware string comparison (e.g. sorts "Ü" like "U" in German),
	 * falls back to natural sorting if the intl extension isn't available
	 */
	protected function comparator(): callable
	{
		if (!class_exists(Collator::class)) {
			return strnatcasecmp(...);
		}

		$locale = $this->kirby->language()?->locale(LC_COLLATE) ?? $this->kirby->panelLanguage();
		// e.g. `de_DE.UTF-8` > `de_DE`
		$collator = new Collator(is_string($locale) ? Meta::normalizeLocale($locale, '_') : 'en');
		$collator->setAttribute(Collator::NUMERIC_COLLATION, Collator::ON);
		// ignore punctuation, e.g. titles starting with quotes
		$collator->setAttribute(Collator::ALTERNATE_HANDLING, Collator::SHIFTED);

		return fn (string $a, string $b) => $collator->compare($a, $b);
	}

	/**
	 * Search, sorting & filter of the table from the query of the view, unknown values are ignored
	 *
	 * @return array{search: string, sort: string|null, dir: string, issue: string|null}
	 */
	protected function query(array $sortable, array $issues): array
	{
		$request = $this->kirby->request();
		$sort = $request->get('sort');
		$issue = $request->get('issue');

		return [
			'search' => trim((string)$request->get('search', '')),
			'sort' => in_array($sort, $sortable, true) ? $sort : null,
			'dir' => $request->get('dir') === 'desc' ? 'desc' : 'asc',
			'issue' => in_array($issue, $issues, true) ? $issue : null,
		];
	}

	/**
	 * Entries with any value containing the search term (case-insensitive).
	 * Values are resolved in the order of the keys & only until one matches,
	 * so cheap values should come first
	 *
	 * @param \Closure(mixed $entry, string $key): string $value
	 */
	protected function search(array $entries, string $search, array $keys, Closure $value): array
	{
		if ($search === '') {
			return $entries;
		}

		return array_values(array_filter($entries, function ($entry) use ($search, $keys, $value) {
			foreach ($keys as $key) {
				if (Str::contains($value($entry, $key), $search, true)) {
					return true;
				}
			}

			return false;
		}));
	}

	/**
	 * Sorts the entries by the given (plain text) value
	 */
	protected function sort(array $entries, Closure $value, string $dir): array
	{
		$compare = $this->comparator();
		usort($entries, fn ($a, $b) => $compare($value($a), $value($b)) * ($dir === 'desc' ? -1 : 1));

		return $entries;
	}

	/**
	 * Pagination props & the entries of the current table page
	 *
	 * @return array{0: array, 1: array}
	 */
	protected function paginate(array $entries): array
	{
		// clamp the page, as the number of results might have changed (e.g. after deleting pages)
		$lastPage = max(1, (int)ceil(count($entries) / self::LIMIT));
		$page = min($lastPage, max(1, (int)$this->kirby->request()->get('page', 1)));

		return [
			['page' => $page, 'limit' => self::LIMIT, 'total' => count($entries)],
			array_slice($entries, ($page - 1) * self::LIMIT, self::LIMIT),
		];
	}
}
