<?php

namespace tobimori\Seo\Views;

use Closure;
use Collator;
use Kirby\Api\Controller\Changes as ChangesController;
use Kirby\Cms\App;
use Kirby\Cms\File;
use Kirby\Cms\Language;
use Kirby\Cms\ModelWithContent;
use Kirby\Cms\Page;
use Kirby\Cms\Pages;
use Kirby\Content\Changes;
use Kirby\Content\LockedContentException;
use Kirby\Content\VersionId;
use Kirby\Exception\Exception;
use Kirby\Exception\NotFoundException;
use Kirby\Exception\PermissionException;
use Kirby\Panel\Ui\Buttons\ViewButtons;
use Kirby\Toolkit\I18n;
use tobimori\Seo\AltText;
use tobimori\Seo\Audit;
use tobimori\Seo\Meta;
use tobimori\Seo\Seo;

/**
 * Base of the tabs of the SEO area: tables of models (pages, images) whose fields
 * can be edited inline, with the same workflow as editing a model in the Panel.
 *
 * Edits are written to the changes version of the model (which locks it for others)
 * and can be published or discarded per row. All values are read from the changes
 * version (if any), so the tables reflect what editors see in the Panel.
 *
 * Rows are described as entries: `['id' => string, 'model' => ModelWithContent, 'fields' => [...]]`,
 * with the content fields the row may edit
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
	protected array|null $images = null;
	protected bool|null $ai = null;

	public function __construct()
	{
		$this->kirby = App::instance();
	}

	public static function for(string $tab): static
	{
		return match ($tab) {
			'pages' => new PagesView(),
			default => throw new NotFoundException(key: 'view.notFound'),
		};
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
	 * Entry of the row with the given id, `null` if the row isn't part of the table
	 */
	abstract protected function find(string $id): array|null;

	/**
	 * Ids of all rows of the model
	 */
	abstract protected function ids(ModelWithContent $model): array;

	/**
	 * Models with unsaved changes, as tracked by Kirby
	 */
	abstract protected function tracked(Changes $changes): iterable;

	/**
	 * Data of a row in the table
	 */
	abstract protected function row(array $entry): array;

	/**
	 * Converts the edited columns of a row to the values of its content fields
	 */
	abstract protected function input(array $entry, array $columns): array;

	/**
	 * Values that might change with any edit (e.g. the number of issues), sent along with updated rows
	 */
	abstract protected function live(): array;

	protected function notFound(string $id): NotFoundException
	{
		return new NotFoundException(key: 'page.notFound', data: ['slug' => $id]);
	}

	/**
	 * Name of the row in the list of changes
	 */
	protected function label(array $entry): string
	{
		return (string)$entry['model']->title()->value();
	}

	/**
	 * Rows with unsaved changes of their fields (not only on the current table page)
	 * that can be published by the current user. Uses Kirby's tracked changes
	 * (same as the Panel's changes dialog) instead of checking every model
	 */
	public function changes(): array
	{
		$tracked = new Changes();

		if ($tracked->cacheExists() === false) {
			$tracked->generateCache();
		}

		$changes = [];

		foreach ($this->tracked($tracked) as $model) {
			// someone else is editing the model
			if ($model->version('changes')->isLocked('*') || !$model->permissions()->can('update')) {
				continue;
			}

			foreach ($this->ids($model) as $id) {
				if (($entry = $this->find($id)) && $this->fieldChanges($entry) !== []) {
					$changes[] = [
						'id' => $id,
						'link' => $model->panel()->url(true),
						'text' => $this->label($entry),
					];
				}
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
				if (is_string($id) && ($entry = $this->find($id))) {
					$rows[$id] = $this->row($entry);
				}
			}

			return [
				'rows' => $rows,
				'changes' => $this->changes(),
				// edits might fix (or cause) issues of other rows, e.g. duplicates
				...$this->live(),
			];
		});
	}

	/**
	 * Saves values of multiple rows to the changes versions of their models in a single request,
	 * using the same logic as the Panel when editing a model
	 *
	 * @param array $changes List of `['id' => 'row-id', 'column' => 'metaTitle', 'value' => '…']`
	 */
	public function save(array $changes): array
	{
		if (!static::canAccess()) {
			throw new PermissionException(key: 'access.view');
		}

		// one save per row, with all of its changed columns
		$input = [];
		foreach ($changes as $change) {
			if (is_string($change['id'] ?? null) && is_string($change['column'] ?? null) && array_key_exists('value', $change)) {
				$input[$change['id']][$change['column']] = $change['value'];
			}
		}

		$errors = [];

		foreach ($input as $id => $columns) {
			try {
				// only rows listed in the overview can be edited
				$entry = $this->find($id) ?? throw $this->notFound($id);

				// Kirby would store values of fields that don't exist in the blueprint as well
				$values = array_intersect_key($this->input($entry, $columns), array_flip($entry['fields']));

				if ($values !== []) {
					ChangesController::save(model: $entry['model'], input: $values);
				}
			} catch (Exception $e) {
				$errors[$id] = $this->error($e);
			}
		}

		return [
			...$this->rows(array_keys($input)),
			'errors' => $errors,
		];
	}

	/**
	 * Publishes the unsaved fields of the given rows,
	 * other unsaved fields stay in the changes versions
	 */
	public function publish(array $ids): array
	{
		return $this->apply($ids, function (array $entry) {
			if ($values = $this->fieldChanges($entry)) {
				$model = $entry['model']->update(input: $values, languageCode: Language::ensure('current')->code(), validate: true);
				$this->resetFields([...$entry, 'model' => $model]);
			}
		});
	}

	/**
	 * Discards the unsaved fields of the given rows,
	 * other unsaved fields stay in the changes versions
	 */
	public function discard(array $ids): array
	{
		return $this->apply($ids, function (array $entry) {
			if (!$entry['model']->permissions()->can('update')) {
				throw new PermissionException(key: 'version.discard.permission');
			}

			$this->resetFields($entry);
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

	protected function apply(array $ids, Closure $action): array
	{
		if (!static::canAccess()) {
			throw new PermissionException(key: 'access.view');
		}

		$ids = array_values(array_filter($ids, 'is_string'));
		$errors = [];

		foreach ($ids as $id) {
			try {
				$entry = $this->find($id) ?? throw $this->notFound($id);
				$lock = $entry['model']->version('changes')->lock('*');

				if ($lock->isLocked()) {
					throw new LockedContentException(lock: $lock, key: 'content.lock.update');
				}

				$action($entry);
			} catch (Exception $e) {
				$errors[$id] = $this->error($e);
			}
		}

		return [
			...$this->rows($ids),
			'errors' => $errors,
		];
	}

	protected function error(Exception $e): array
	{
		return [
			'key' => $e->getKey(),
			'message' => $e->getMessage(),
			'details' => $e->getDetails(),
		];
	}

	/**
	 * Unsaved values of the row's fields that differ from the published ones
	 */
	protected function fieldChanges(array $entry): array
	{
		$language = Language::ensure('current');
		$changes = $entry['model']->version('changes');

		if (!$changes->exists($language)) {
			return [];
		}

		$unsaved = $changes->read($language) ?? [];
		$latest = $entry['model']->version('latest')->read($language) ?? [];
		$values = [];

		foreach ($entry['fields'] as $field) {
			$key = strtolower($field);

			if (array_key_exists($key, $unsaved) && (string)$unsaved[$key] !== (string)($latest[$key] ?? '')) {
				$values[$key] = $unsaved[$key];
			}
		}

		return $values;
	}

	/**
	 * Sets the row's fields of the changes version back to the published values
	 * and removes the changes version if nothing else has changed
	 */
	protected function resetFields(array $entry): void
	{
		$language = Language::ensure('current');
		$changes = $entry['model']->version('changes');

		if (!$changes->exists($language)) {
			return;
		}

		$latest = $entry['model']->version('latest')->read($language) ?? [];
		$keys = array_map('strtolower', $entry['fields']);

		$changes->update(
			array_combine($keys, array_map(fn ($key) => $latest[$key] ?? null, $keys)),
			$language
		);

		if ($changes->isIdentical('latest', $language)) {
			$changes->delete($language);
		}
	}

	/**
	 * Unsaved value of a field (if any), otherwise the published one.
	 * Edits are based on it, also outside of `VersionId::render()`
	 */
	protected function current(ModelWithContent $model, string $field): string
	{
		$changes = $model->version('changes');
		$version = $changes->exists('current') ? $changes : $model->version('latest');

		return (string)$version->content('current')->get($field)->value();
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
	 * Alt texts of the images of the site & the pages of the overview, one entry per alt text field:
	 * `alt-text` fields, or plain `alt` fields for blueprints without one (supported by `toAltText()` as well)
	 *
	 * @return array<string, array{id: string, model: \Kirby\Cms\File, fields: array<string>, field: string, type: string}>
	 */
	protected function images(): array
	{
		if ($this->images !== null) {
			return $this->images;
		}

		$this->images = [];
		$fields = [];

		foreach ([$this->kirby->site(), ...$this->pages()] as $parent) {
			foreach ($parent->files() as $file) {
				if ($file->type() !== 'image' || !$file->isListable()) {
					continue;
				}

				$fields[$file->template() ?? 'default'] ??= $this->altFields($file);

				foreach ($fields[$file->template() ?? 'default'] as $name => $type) {
					$id = static::imageId($file, $name);
					$this->images[$id] = [
						'id' => $id,
						'model' => $file,
						'fields' => [$name],
						'field' => $name,
						'type' => $type,
					];
				}
			}
		}

		return $this->images;
	}

	/**
	 * Rows of images are per alt text field, as a file might have multiple of them
	 */
	protected static function imageId(File $file, string $field): string
	{
		return "{$file->id()}#{$field}";
	}

	/**
	 * Alt text fields of the file's blueprint & their types
	 *
	 * @return array<string, string>
	 */
	protected function altFields(File $file): array
	{
		$fields = $file->blueprint()->fields();
		$alt = array_filter($fields, fn ($field) => ($field['type'] ?? null) === 'alt-text');

		if ($alt !== []) {
			return array_map(fn () => 'alt-text', $alt);
		}

		return isset($fields['alt']) ? ['alt' => $fields['alt']['type'] ?? 'text'] : [];
	}

	protected function altText(array $entry): AltText
	{
		return AltText::parse($entry['model']->content()->get($entry['field'])->value());
	}

	/**
	 * Number of alt texts that are missing or generated by AI (& not reviewed yet)
	 */
	protected function imageStats(): array
	{
		$stats = ['total' => 0, 'missing' => 0, 'ai' => 0];

		foreach ($this->images() as $entry) {
			$alt = $this->altText($entry);
			$stats['total']++;

			if ($alt->isMissing()) {
				$stats['missing']++;
			} elseif ($alt->isAiGenerated()) {
				$stats['ai']++;
			}
		}

		return $stats;
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

	protected function canUseAi(): bool
	{
		return $this->ai ??= Seo::option('components.ai')::enabled()
			&& $this->kirby->user()?->role()->permissions()->for('tobimori.seo', 'ai') !== false;
	}

	/**
	 * Lock, permissions & translation state of a row, same conditions as editing the model in the Panel
	 */
	protected function state(ModelWithContent $model): array
	{
		$version = $model->version('changes');

		// someone else is editing the model right now
		$lock = $version->lock('*');
		$lock = $lock->isLocked() ? $lock->toArray() : null;

		return [
			'lock' => $lock,
			'editable' => $model->permissions()->can('update') && $lock === null,
			// models without a translation in the current language show the content
			// of the default language (like everywhere in Kirby), until someone edits them
			'translated' => $version->exists('current') || $model->version('latest')->exists('current'),
		];
	}
}
