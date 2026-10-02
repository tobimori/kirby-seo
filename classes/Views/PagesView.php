<?php

namespace tobimori\Seo\Views;

use Kirby\Cms\ModelWithContent;
use Kirby\Cms\Page;
use Kirby\Content\Changes;
use Kirby\Content\VersionId;
use Kirby\Exception\PermissionException;
use Kirby\Panel\Ui\Item\PageItem;
use Kirby\Toolkit\I18n;
use Kirby\Toolkit\Str;
use Kirby\Toolkit\V;
use tobimori\Seo\Audit;
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
				$pages = $this->sort($pages, fn (Page $page) => $this->value($page, $sort), $dir);
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
					'summary' => $this->audit()->summary(),
					'issue' => $issue,
					'ai' => $this->canUseAi(),
					'gsc' => $this->hasSearchConsole(),
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
			'fields' => array_values(array_filter(self::EDITABLE, fn ($key) => $page->blueprint()->field($key) !== null)),
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
