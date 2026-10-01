<?php

namespace tobimori\Seo\Views;

use Closure;
use Collator;
use Kirby\Api\Controller\Changes as ChangesController;
use Kirby\Cms\App;
use Kirby\Cms\File;
use Kirby\Cms\Find;
use Kirby\Cms\Page;
use Kirby\Cms\Pages;
use Kirby\Cms\Language;
use Kirby\Content\Changes;
use Kirby\Content\LockedContentException;
use Kirby\Content\VersionId;
use Kirby\Exception\Exception;
use Kirby\Exception\NotFoundException;
use Kirby\Exception\PermissionException;
use Kirby\Panel\Ui\Buttons\ViewButtons;
use Kirby\Panel\Ui\Item\PageItem;
use Kirby\Toolkit\I18n;
use Kirby\Toolkit\Pagination;
use Kirby\Toolkit\Str;
use Kirby\Toolkit\V;
use tobimori\Seo\AltText;
use tobimori\Seo\Audit;
use tobimori\Seo\Meta;
use tobimori\Seo\Seo;

/**
 * Panel view listing the metadata of all pages
 *
 * All values are read from the unsaved changes version (if any), so the table
 * reflects what editors see in the Panel. Meta values are resolved lazily:
 * only for the rows of the current table page, unless searching or sorting needs them.
 */
class OverviewView
{
	public const SORTABLE = ['title', 'metaTitle', 'metaDescription', 'ogDescription', 'template'];
	public const EDITABLE = ['metaTitle', 'metaDescription', 'ogDescription'];
	// cheap values first, so searching can skip resolving the meta cascade for many pages
	public const SEARCHABLE = ['id', 'title', 'template', 'metaTitle', 'metaDescription', 'ogDescription'];
	public const LIMIT = 50;
	// tabs & their icons
	public const TABS = ['pages' => 'page', 'images' => 'image', 'links' => 'url'];

	protected App $kirby;

	/**
	 * Meta instances & resolved meta values per page, for the current request
	 */
	protected array $metas = [];
	protected array $resolved = [];
	protected Audit|null $audit = null;
	protected Pages|null $pages = null;
	protected bool|null $ai = null;

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
	 * Pages with unsaved changes of their meta fields (not only on the current table page)
	 * that can be published by the current user. Uses Kirby's tracked changes
	 * (same as the Panel's changes dialog) instead of checking every page
	 */
	public function changes(): array
	{
		$tracked = new Changes();

		if ($tracked->cacheExists() === false) {
			$tracked->generateCache();
		}

		$changes = [];

		foreach ($tracked->pages() as $page) {
			$version = $page->version('changes');

			if (
				$this->isListed($page)
				&& $this->metaChanges($page) !== []
				&& !$version->isLocked('*')
				&& $page->permissions()->can('update')
			) {
				$changes[] = [
					'id' => $page->id(),
					'link' => $page->panel()->url(true),
					'text' => $page->title()->value(),
				];
			}
		}

		return $changes;
	}

	/**
	 * Current state of the given rows, e.g. to update locks or values after saving
	 * without reloading (and resorting) the whole table
	 */
	public function rows(array $ids): array
	{
		if (!static::canAccess()) {
			throw new PermissionException(key: 'access.view');
		}

		return VersionId::render('changes', function () use ($ids) {
			$rows = [];

			foreach ($ids as $id) {
				if (is_string($id) && ($page = $this->kirby->page($id)) && $this->isListed($page)) {
					$rows[$id] = $this->row($page);
				}
			}

			return [
				'rows' => $rows,
				'changes' => $this->changes(),
				// edits might fix (or cause) issues of other pages, e.g. duplicates
				'summary' => $this->audit()->summary(),
				'stats' => $this->audit()->stats(),
			];
		});
	}

	/**
	 * Saves values of multiple pages to their changes versions in a single request,
	 * using the same logic as the Panel when editing a page
	 *
	 * @param array $changes List of `['id' => 'page-id', 'column' => 'metaTitle', 'value' => '…']`
	 */
	public function save(array $changes): array
	{
		if (!static::canAccess()) {
			throw new PermissionException(key: 'access.view');
		}

		// one save per page, with all of its changed fields
		$input = [];
		foreach ($changes as $change) {
			if (
				is_string($change['id'] ?? null)
				&& in_array($change['column'] ?? null, self::EDITABLE, true)
				&& is_string($change['value'] ?? null)
			) {
				$input[$change['id']][$change['column']] = $change['value'];
			}
		}

		$errors = [];

		foreach ($input as $id => $values) {
			try {
				$page = Find::page($id);

				// only pages listed in the overview can be edited
				if (!$this->isListed($page)) {
					throw new NotFoundException(key: 'page.notFound', data: ['slug' => $id]);
				}

				// Kirby would store values of fields that don't exist in the blueprint as well
				$values = array_filter(
					$values,
					fn ($key) => $page->blueprint()->field($key) !== null,
					ARRAY_FILTER_USE_KEY
				);

				if ($values !== []) {
					ChangesController::save(model: $page, input: $values);
				}
			} catch (Exception $e) {
				$errors[$id] = [
					'key' => $e->getKey(),
					'message' => $e->getMessage(),
					'details' => $e->getDetails(),
				];
			}
		}

		return [
			...$this->rows(array_keys($input)),
			'errors' => $errors,
		];
	}

	/**
	 * Publishes the unsaved meta fields of the given pages,
	 * other unsaved fields stay in their changes versions
	 */
	public function publish(array $ids): array
	{
		return $this->apply($ids, function (Page $page) {
			if ($values = $this->metaChanges($page)) {
				$page = $page->update(input: $values, languageCode: Language::ensure('current')->code(), validate: true);
				$this->resetMeta($page);
			}
		});
	}

	/**
	 * Discards the unsaved meta fields of the given pages,
	 * other unsaved fields stay in their changes versions
	 */
	public function discard(array $ids): array
	{
		return $this->apply($ids, function (Page $page) {
			if (!$page->permissions()->can('update')) {
				throw new PermissionException(key: 'version.discard.permission');
			}

			$this->resetMeta($page);
		});
	}

	protected function apply(array $ids, Closure $action): array
	{
		if (!static::canAccess()) {
			throw new PermissionException(key: 'access.view');
		}

		$ids = array_values(array_filter($ids, 'is_string'));
		$errors = [];

		foreach ($ids as $id) {
			try {
				$page = Find::page($id);

				if (!$this->isListed($page)) {
					throw new NotFoundException(key: 'page.notFound', data: ['slug' => $id]);
				}

				$lock = $page->version('changes')->lock('*');

				if ($lock->isLocked()) {
					throw new LockedContentException(lock: $lock, key: 'content.lock.update');
				}

				$action($page);
			} catch (Exception $e) {
				$errors[$id] = [
					'key' => $e->getKey(),
					'message' => $e->getMessage(),
					'details' => $e->getDetails(),
				];
			}
		}

		return [
			...$this->rows($ids),
			'errors' => $errors,
		];
	}

	/**
	 * Unsaved values of the meta fields that differ from the published ones
	 */
	protected function metaChanges(Page $page): array
	{
		$language = Language::ensure('current');
		$changes = $page->version('changes');

		if (!$changes->exists($language)) {
			return [];
		}

		$unsaved = $changes->read($language) ?? [];
		$latest = $page->version('latest')->read($language) ?? [];
		$values = [];

		foreach (self::EDITABLE as $field) {
			$key = strtolower($field);

			if (array_key_exists($key, $unsaved) && (string)$unsaved[$key] !== (string)($latest[$key] ?? '')) {
				$values[$key] = $unsaved[$key];
			}
		}

		return $values;
	}

	/**
	 * Sets the meta fields of the changes version back to the published values
	 * and removes the changes version if nothing else has changed
	 */
	protected function resetMeta(Page $page): void
	{
		$language = Language::ensure('current');
		$changes = $page->version('changes');

		if (!$changes->exists($language)) {
			return;
		}

		$latest = $page->version('latest')->read($language) ?? [];
		$keys = array_map('strtolower', self::EDITABLE);

		$changes->update(
			array_combine($keys, array_map(fn ($key) => $latest[$key] ?? null, $keys)),
			$language
		);

		if ($changes->isIdentical('latest', $language)) {
			$changes->delete($language);
		}
	}

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
			// show only pages with the given issue type (or any title/description issue),
			// or sharing the same title/description
			$issue = in_array($request->get('issue'), [...Audit::TYPES, ...Audit::KINDS], true) ? $request->get('issue') : null;
			$group = $this->audit()->group($hash = (string)$request->get('group')) ? $hash : null;

			$pages = $this->pages()->values();

			if ($issue) {
				$pages = array_values(array_filter($pages, fn ($page) => $this->audit()->has($page, $issue)));
			}

			if ($group) {
				$pages = array_values(array_filter(
					$pages,
					fn ($page) => in_array($page->id(), $this->audit()->group($group)['pages'], true)
				));
			}

			$pages = $this->search($pages, $search);

			if ($sort) {
				$compare = $this->comparator();
				usort(
					$pages,
					fn ($a, $b) => $compare($this->value($a, $sort), $this->value($b, $sort)) * ($dir === 'desc' ? -1 : 1)
				);
			}

			// clamp the page, as the number of results might have changed (e.g. after deleting pages)
			$lastPage = max(1, (int)ceil(count($pages) / self::LIMIT));
			$pagination = new Pagination([
				'page' => min($lastPage, max(1, (int)$request->get('page', 1))),
				'limit' => self::LIMIT,
				'total' => count($pages),
			]);

			return [
				'component' => 'k-seo-overview-view',
				'title' => I18n::translate('seo.overview.title'),
				'props' => [
					...$this->layout('pages'),
					'columns' => $this->columns(),
					// only the visible rows resolve all of their values
					'rows' => array_map(
						$this->row(...),
						array_slice($pages, $pagination->offset(), $pagination->limit())
					),
					'changes' => $this->changes(),
					'pagination' => [
						'page' => $pagination->page(),
						'limit' => $pagination->limit(),
						'total' => $pagination->total(),
					],
					'ids' => array_map(fn (Page $page) => $page->id(), $pages),
					'summary' => $this->audit()->summary(),
					'issue' => $issue,
					'ai' => $this->canUseAi(),
					'group' => $group ? [
						'hash' => $group,
						'kind' => $this->audit()->group($group)['kind'],
						'text' => $this->audit()->group($group)['text'],
						'count' => count($this->audit()->group($group)['pages']),
					] : null,
					'search' => $search,
					'sort' => $sort,
					'dir' => $dir,
				]
			];
		});
	}

	/**
	 * Tabs that don't have their own view yet
	 */
	public function tab(string $tab): array
	{
		if (!static::canAccess()) {
			throw new PermissionException(key: 'access.view');
		}

		return VersionId::render('changes', fn () => [
			'component' => 'k-seo-tab-view',
			'title' => I18n::translate('seo.overview.title'),
			'props' => $this->layout($tab),
		]);
	}

	/**
	 * Props shared by all tabs: header buttons, stats & tabs
	 */
	protected function layout(string $tab): array
	{
		return [
			// `panel.content` expects the content props of a model view, e.g. the languages dropdown
			// (and plugins building on it) unlocks the content via `{api}/changes/unlock` before
			// switching. The view buttons are bound to the site, so is this; the overview itself
			// has no content: edits are saved per page & their locks are released by the view
			'api' => 'site',
			'lock' => ['isLocked' => false],
			'versions' => ['latest' => [], 'changes' => []],
			// configurable via `panel.viewButtons.seo.overview`, like the views of Kirby itself
			'buttons' => fn () => ViewButtons::view('seo.overview', model: $this->kirby->site())
				->defaults('languages')
				->render(),
			'stats' => [
				...$this->audit()->stats(),
				'images' => $this->imageStats(),
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
	 * Alt texts of all images of published pages & the site: `alt-text` fields,
	 * or plain `alt` fields for blueprints without one (supported by `toAltText()` as well)
	 */
	protected function imageStats(): array
	{
		$stats = ['total' => 0, 'missing' => 0, 'ai' => 0];
		$fields = [];
		$files = $this->kirby->site()->files()->add($this->kirby->site()->index()->files());

		foreach ($files as $file) {
			if ($file->type() !== 'image') {
				continue;
			}

			$template = $file->template() ?? 'default';
			$fields[$template] ??= $this->altFields($file);

			foreach ($fields[$template] as $name) {
				$alt = AltText::parse($file->content()->get($name)->value());
				$stats['total']++;

				if ($alt->isMissing()) {
					$stats['missing']++;
				} elseif ($alt->isAiGenerated()) {
					$stats['ai']++;
				}
			}
		}

		return $stats;
	}

	protected function altFields(File $file): array
	{
		$fields = $file->blueprint()->fields();
		$alt = array_keys(array_filter($fields, fn ($field) => ($field['type'] ?? null) === 'alt-text'));

		return $alt ?: (isset($fields['alt']) ? ['alt'] : []);
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
	 * All pages with SEO fields the current user is allowed to see in the Panel
	 */
	protected function pages(): Pages
	{
		return $this->pages ??= $this->kirby->site()->index(true)->filter($this->isListed(...));
	}

	/**
	 * Checks of all pages (e.g. for duplicates), shared by all rows
	 */
	protected function audit(): Audit
	{
		return $this->audit ??= new Audit($this->pages(), $this->meta(...));
	}

	/**
	 * Whether the page is part of the overview
	 * (skips e.g. form submissions or other pages that aren't meant to be public)
	 */
	protected function isListed(Page $page): bool
	{
		return $page->isListable()
			&& $page->blueprint()->field('metaTitle') !== null
			&& $page->blueprint()->field('metaDescription') !== null;
	}

	/**
	 * Not using $page->metadata(), as page models of other plugins might define their own method with that name
	 */
	protected function meta(Page $page): Meta
	{
		return $this->metas[$page->id()] ??= new (Seo::option('components.meta'))($page);
	}

	/**
	 * Resolves a meta value & its source once per request
	 *
	 * @return array{field: \Kirby\Content\Field, source: string|null}
	 */
	protected function resolve(Page $page, string $key, array $exclude = []): array
	{
		return $this->resolved[$page->id()][$key . '|' . implode(',', $exclude)] ??= $this->meta($page)->resolve($key, $exclude);
	}

	/**
	 * Plain text value of a searchable/sortable column
	 */
	protected function value(Page $page, string $key): string
	{
		return match ($key) {
			'id' => $page->id(),
			'title' => (string)$page->title()->value(),
			'template' => (string)$page->blueprint()->title(),
			default => Str::unhtml((string)$this->resolve($page, $key)['field']->value()),
		};
	}

	/**
	 * @param array<Page> $pages
	 */
	protected function search(array $pages, string $search): array
	{
		if ($search === '') {
			return $pages;
		}

		return array_values(array_filter(
			$pages,
			function (Page $page) use ($search) {
				foreach (self::SEARCHABLE as $key) {
					if (Str::contains($this->value($page, $key), $search, true)) {
						return true;
					}
				}

				return false;
			}
		));
	}

	/**
	 * Columns with a `toggle` label can be shown/hidden by the user,
	 * columns with `hidden: true` are hidden until the user enables them,
	 * columns with `resizable: false` keep their width.
	 * Text columns don't set a width, so they share the available space equally
	 */
	protected function columns(): array
	{
		$columns = [
			// a single indicator per page in front, details on hover & click
			'checks' => [
				'label' => ' ',
				'mobile' => true,
				'resizable' => false,
				'toggle' => I18n::translate('seo.overview.columns.checks'),
				'type' => 'seo-checks',
				'width' => 'var(--table-row-height)'
			],
			'image' => [
				'hidden' => true,
				'label' => ' ',
				'mobile' => true,
				'resizable' => false,
				'toggle' => I18n::translate('seo.overview.columns.image'),
				'type' => 'image',
				'width' => 'var(--table-row-height)'
			],
			'title' => [
				'label' => I18n::translate('title'),
				'mobile' => true,
				'sortable' => true,
				'type' => 'seo-page'
			],
			'metaTitle' => [
				'editable' => true,
				'label' => I18n::translate('seo.overview.columns.metaTitle'),
				'sortable' => true,
				'toggle' => I18n::translate('seo.overview.columns.metaTitle'),
				'type' => 'seo-meta'
			],
			'metaDescription' => [
				'editable' => true,
				'label' => I18n::translate('seo.overview.columns.metaDescription'),
				'sortable' => true,
				'toggle' => I18n::translate('seo.overview.columns.metaDescription'),
				'type' => 'seo-meta'
			],
			'ogDescription' => [
				'editable' => true,
				'hidden' => true,
				'label' => I18n::translate('seo.overview.columns.ogDescription'),
				'sortable' => true,
				'toggle' => I18n::translate('seo.overview.columns.ogDescription'),
				'type' => 'seo-meta'
			],
			'ogImage' => [
				'label' => I18n::translate('seo.overview.columns.ogImage'),
				'toggle' => I18n::translate('seo.overview.columns.ogImage'),
				'type' => 'seo-image',
				'width' => '7rem'
			],
			'template' => [
				'hidden' => true,
				'label' => I18n::translate('template'),
				'sortable' => true,
				'toggle' => I18n::translate('template'),
				'type' => 'text',
				'width' => '10rem'
			],
		];

		if (Seo::option('robots.enabled')) {
			$columns['robots'] = [
				'label' => ' ',
				'mobile' => true,
				'resizable' => false,
				'toggle' => I18n::translate('seo.overview.columns.robots'),
				'type' => 'seo-robots',
				'width' => 'var(--table-row-height)'
			];
		}

		$columns['flag'] = [
			'label' => ' ',
			'mobile' => true,
			'resizable' => false,
			'type' => 'flag',
			'width' => 'var(--table-row-height)'
		];

		return $columns;
	}


	protected function row(Page $page): array
	{
		$version = $page->version('changes');
		$hasChanges = $version->exists('current');
		$hasMetaChanges = $this->metaChanges($page) !== [];

		// someone else is editing the page right now
		$lock = $version->lock('*');
		$lock = $lock->isLocked() ? $lock->toArray() : null;

		// same conditions as for editing the page in the Panel
		$editable = $page->permissions()->can('update') && $lock === null;

		return [
			...(new PageItem(page: $page))->props(),
			'changes' => $hasMetaChanges,
			'editable' => $editable,
			'lock' => $lock,
			'selectable' => $lock === null,
			'title' => [
				'text' => $this->value($page, 'title'),
				'href' => $page->panel()->url(true),
				'path' => '/' . $page->uri(),
				// the lock already tells that someone else is editing
				'changes' => $hasMetaChanges && $lock === null,
				// pages without a translation in the current language show the content
				// of the default language (like everywhere in Kirby), until someone edits them
				'translated' => $hasChanges || $page->version('latest')->exists('current'),
			],
			'metaTitle' => $this->metaField($page, 'metaTitle', $editable),
			'metaDescription' => $this->metaField($page, 'metaDescription', $editable),
			'ogDescription' => $this->metaField($page, 'ogDescription', $editable),
			'ogImage' => $this->ogImage($page),
			'checks' => $this->audit()->page($page),
			'template' => $this->value($page, 'template'),
			'robots' => Seo::option('robots.enabled') ? $this->robots($this->meta($page)) : null,
		];
	}

	/**
	 * Resolved value & source for display, plus the page's own value for editing
	 */
	protected function metaField(Page $page, string $key, bool $editable): array
	{
		$own = (string)$page->content()->get($key)->value();
		// what the page would show without its own value, same as the placeholder in the page form
		$fallback = $this->resolve($page, $key, ['fields']);

		return [
			'text' => $this->value($page, $key),
			'source' => $this->resolve($page, $key)['source'],
			// raw value, as writer fields store HTML
			'value' => in_array($own, Meta::DEFAULT_VALUES, true) ? '' : $own,
			'placeholder' => Str::unhtml((string)$fallback['field']->value()),
			'placeholderSource' => $fallback['source'],
			'editable' => $editable && $page->blueprint()->field($key) !== null,
			'ai' => $editable && $this->canUseAi() && !empty($page->blueprint()->field($key)['ai'] ?? false),
		];
	}

	protected function canUseAi(): bool
	{
		return $this->ai ??= Seo::option('components.ai')::enabled()
			&& $this->kirby->user()?->role()->permissions()->for('tobimori.seo', 'ai') !== false;
	}

	protected function ogImage(Page $page): array|null
	{
		['field' => $field, 'source' => $source] = $this->resolve($page, 'ogImage');

		if ($field->isEmpty()) {
			return null;
		}

		$file = $field->toFile();
		$src = match (true) {
			$file?->isResizable() === true => $file->crop(240, 126)->url(),
			$file !== null => $file->url(),
			// custom URL, anything else (e.g. a reference to a deleted file) can't be shown
			V::url($field->value()) => $field->value(),
			default => null,
		};

		return $src ? ['src' => $src, 'source' => $source] : null;
	}

	/**
	 * Same states as the robots view button on pages
	 */
	protected function robots(Meta $meta): array
	{
		$robots = $meta->robots();

		$state = match (true) {
			Str::contains($robots, 'noindex') => 'noindex',
			Str::contains($robots, 'no') => 'any',
			default => 'index',
		};

		return [
			'state' => $state,
			'text' => I18n::translate("seo.fields.robots.indicator.{$state}"),
			'value' => $robots,
		];
	}
}
