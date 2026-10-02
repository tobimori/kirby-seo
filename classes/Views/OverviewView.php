<?php

namespace tobimori\Seo\Views;

use Closure;
use Collator;
use Kirby\Cms\App;
use Kirby\Cms\Page;
use Kirby\Cms\Pages;
use Kirby\Panel\Ui\Buttons\ViewButtons;
use Kirby\Toolkit\I18n;
use tobimori\Seo\Audit;
use tobimori\Seo\ImageAudit;
use tobimori\Seo\Links\Report;
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
	protected Audit|null $audit = null;
	protected Pages|null $pages = null;
	protected ImageAudit|null $images = null;

	public function __construct()
	{
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
			&& $page->blueprint()->field('metaTitle') !== null
			&& $page->blueprint()->field('metaDescription') !== null;
	}

	/**
	 * Checks of all pages (e.g. for duplicates), shared by all rows
	 */
	protected function audit(): Audit
	{
		return $this->audit ??= new Audit($this->pages(), $this->meta(...));
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
	protected function images(): ImageAudit
	{
		return $this->images ??= new ImageAudit();
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
