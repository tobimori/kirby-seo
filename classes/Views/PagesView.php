<?php

namespace tobimori\Seo\Views;

use Kirby\Cms\ModelWithContent;
use Kirby\Cms\Page;
use Kirby\Content\Changes;
use Kirby\Content\VersionId;
use Kirby\Panel\Ui\Item\PageItem;
use Kirby\Toolkit\I18n;
use Kirby\Toolkit\Str;
use Kirby\Toolkit\V;
use tobimori\Seo\Audit;
use tobimori\Seo\Buttons\RobotsViewButton;
use tobimori\Seo\Meta;
use tobimori\Seo\Seo;

/**
 * Panel view listing the metadata of all pages
 *
 * Meta values are resolved lazily: only for the rows of the current table page,
 * unless searching or sorting needs them.
 */
class PagesView extends EditableOverviewView
{
	public const SORTABLE = ['title', 'metaTitle', 'metaDescription', 'ogDescription', 'template'];
	public const EDITABLE = ['metaTitle', 'metaDescription', 'ogDescription'];
	// cheap values first, so searching can skip resolving the meta cascade for many pages
	public const SEARCHABLE = ['id', 'title', 'template', 'metaTitle', 'metaDescription', 'ogDescription'];

	/**
	 * Resolved meta values per page, for the current request
	 */
	protected array $resolved = [];

	public function load(): array
	{
		return VersionId::render('changes', function () {
			['search' => $search, 'sort' => $sort, 'dir' => $dir, 'issue' => $issue] = $this->query(
				self::SORTABLE,
				[...Audit\Pages::TYPES, ...Audit\Pages::KINDS]
			);
			$group = $this->audit()->group($hash = (string)$this->kirby->request()->get('group'));
			$members = array_flip($group['pages'] ?? []);

			// pages with the given issue type (or any title/description issue), or sharing the same title/description
			$pages = array_values(array_filter(
				$this->pages()->values(),
				fn (Page $page) => ($issue === null || $this->audit()->has($page, $issue))
					&& ($group === null || isset($members[$page->id()]))
			));

			$pages = $this->search($pages, $search, self::SEARCHABLE, $this->indexed(...));

			if ($sort) {
				$pages = $this->sort($pages, fn (Page $page) => $this->indexed($page, $sort), $dir);
			}

			[$pagination, $visible] = $this->paginate($pages);

			return [
				'component' => 'k-seo-overview-view',
				'title' => I18n::translate('seo.overview.title'),
				'props' => [
					...$this->layout('pages'),
					'columns' => $this->columns(),
					// only the visible rows resolve all of their values
					'rows' => array_map(fn (Page $page) => $this->row($this->entry($page)), $visible),
					'changes' => $this->changes(),
					'pagination' => $pagination,
					'ids' => array_map(fn (Page $page) => $page->id(), $pages),
					// severity of each issue type/state, for the filters
					'severity' => Audit\Pages::SEVERITY,
					'summary' => $this->audit()->summary(),
					'issue' => $issue,
					'ai' => $this->canUseAi(),
					'gsc' => $this->hasSearchConsole(),
					'group' => $group ? [
						'hash' => $hash,
						'kind' => $group['kind'],
						'text' => $group['text'],
						'count' => count($group['pages']),
					] : null,
					'search' => $search,
					'sort' => $sort,
					'dir' => $dir,
				]
			];
		});
	}

	protected function find(string $id): array|null
	{
		$page = $this->kirby->page($id);

		return $page && $this->isListedPage($page) ? $this->entry($page) : null;
	}

	protected function entry(Page $page): array
	{
		return [
			'id' => $page->id(),
			'model' => $page,
			'fields' => array_values(array_filter(self::EDITABLE, fn ($key) => $this->blueprint($page)->field($key) !== null)),
		];
	}

	protected function ids(ModelWithContent $model): array
	{
		return [$model->id()];
	}

	protected function tracked(Changes $changes): iterable
	{
		return $changes->pages();
	}

	protected function input(array $entry, array $columns): array
	{
		return array_filter(
			$columns,
			fn ($value, $column) => in_array($column, self::EDITABLE, true) && is_string($value),
			ARRAY_FILTER_USE_BOTH
		);
	}

	/**
	 * Only the stats of the pages, the alt texts don't change when editing pages
	 */
	protected function live(): array
	{
		return [
			'summary' => $this->audit()->summary(),
			'stats' => $this->audit()->stats(),
		];
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
	 * Plain text value of a searchable/sortable column, for searching & sorting all pages:
	 * cached with the checks of the page if possible (see `Audit\Pages::value()`)
	 */
	protected function indexed(Page $page, string $key): string
	{
		return $this->audit()->value($page, $key) ?? $this->value($page, $key);
	}

	/**
	 * Plain text value of a searchable/sortable column, resolved for the visible rows
	 */
	protected function value(Page $page, string $key): string
	{
		return match ($key) {
			'id' => $page->id(),
			'title' => (string)$page->title()->value(),
			'template' => (string)$this->blueprint($page)->title(),
			default => Str::unhtml((string)$this->resolve($page, $key)['field']->value()),
		};
	}

	/**
	 * Columns with a `toggle` label can be shown/hidden by the user,
	 * columns with `hidden: true` are hidden until the user enables them,
	 * columns with `resizable: false` keep their width,
	 * columns with `hideLabel: true` only show their label to screen readers.
	 * Text columns don't set a width, so they share the available space equally
	 */
	protected function columns(): array
	{
		$columns = [
			// a single indicator per page in front, details on hover & click
			'checks' => [
				'hideLabel' => true,
				'label' => I18n::translate('seo.overview.columns.checks'),
				'mobile' => true,
				'resizable' => false,
				'toggle' => I18n::translate('seo.overview.columns.checks'),
				'type' => 'seo-checks',
				'width' => 'var(--table-row-height)'
			],
			'image' => [
				'hidden' => true,
				'hideLabel' => true,
				'label' => I18n::translate('seo.overview.columns.image'),
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
				'hideLabel' => true,
				'label' => I18n::translate('seo.overview.columns.robots'),
				'mobile' => true,
				'resizable' => false,
				'toggle' => I18n::translate('seo.overview.columns.robots'),
				'type' => 'seo-robots',
				'width' => 'var(--table-row-height)'
			];
		}

		$columns['flag'] = [
			'hideLabel' => true,
			'label' => I18n::translate('page.status'),
			'mobile' => true,
			'resizable' => false,
			'type' => 'flag',
			'width' => 'var(--table-row-height)'
		];

		return $columns;
	}

	protected function row(array $entry): array
	{
		/** @var \Kirby\Cms\Page $page */
		$page = $entry['model'];
		['lock' => $lock, 'editable' => $editable, 'translated' => $translated] = $this->state($page);
		$hasChanges = $this->fieldChanges($entry) !== [];

		return [
			...(new PageItem(page: $page))->props(),
			'changes' => $hasChanges,
			'previewUrl' => $page->previewUrl(),
			'editable' => $editable,
			'lock' => $lock,
			'selectable' => $lock === null,
			'title' => [
				'text' => $this->value($page, 'title'),
				'href' => $page->panel()->url(true),
				'path' => '/' . $page->uri(),
				// the lock already tells that someone else is editing
				'changes' => $hasChanges && $lock === null,
				'translated' => $translated,
			],
			...array_combine(self::EDITABLE, array_map(fn ($key) => $this->metaField($entry, $key, $editable), self::EDITABLE)),
			'ogImage' => $this->ogImage($page),
			'checks' => $this->audit()->page($page),
			'template' => $this->value($page, 'template'),
			'robots' => Seo::option('robots.enabled') ? $this->robots($this->meta($page)) : null,
		];
	}

	/**
	 * Resolved value & source for display, plus the page's own value for editing
	 */
	protected function metaField(array $entry, string $key, bool $editable): array
	{
		/** @var \Kirby\Cms\Page $page */
		$page = $entry['model'];
		$editable = $editable && in_array($key, $entry['fields'], true);
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
			'editable' => $editable,
			'ai' => $editable && $this->canUseAi() && !empty($this->blueprint($page)->field($key)['ai'] ?? false),
		];
	}

	protected function hasSearchConsole(): bool
	{
		$gsc = Seo::option('components.gsc');

		return $gsc::hasCredentials() && $gsc::isConnected() && $gsc::property() !== null;
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

		return [...RobotsViewButton::indicator($robots), 'value' => $robots];
	}
}
