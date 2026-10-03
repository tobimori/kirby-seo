<?php

namespace tobimori\Seo\Views;

use Closure;
use Kirby\Api\Controller\Changes as ChangesController;
use Kirby\Cms\Language;
use Kirby\Cms\ModelWithContent;
use Kirby\Content\Changes;
use Kirby\Content\LockedContentException;
use Kirby\Content\VersionId;
use Kirby\Exception\Exception;
use Kirby\Exception\NotFoundException;
use Kirby\Exception\PermissionException;
use tobimori\Seo\Seo;

/**
 * Manages changes versions and content locks for inline edits to overview rows
 */
abstract class EditableOverviewView extends OverviewView
{
	protected bool|null $ai = null;

	public static function for(string $tab): self
	{
		return match ($tab) {
			'pages' => new PagesView(),
			'images' => new ImagesView(),
			default => throw new NotFoundException(key: 'view.notFound'),
		};
	}

	/**
	 * Returns a table entry, or `null` if the row is outside this overview.
	 * `fields` limits the content fields that the row can save, publish, or discard
	 *
	 * @return array{id: string, model: ModelWithContent, fields: array<string>}|null
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
	 * Returns complete rows, not patches, so the client can replace cached rows
	 * without reloading or reordering the table
	 */
	public function rows(array $ids): array
	{
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
	 * Saves permitted row fields to changes versions through Kirby's content controller
	 *
	 * @param array $changes List of `['id' => 'row-id', 'column' => 'metaTitle', 'value' => '…']`
	 */
	public function save(array $changes): array
	{
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

	protected function apply(array $ids, Closure $action): array
	{
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

	protected function canUseAi(): bool
	{
		return $this->ai ??= Seo::option('components.ai')::enabled()
			&& $this->kirby->user()?->role()->permissions()->for('tobimori.seo', 'ai') !== false;
	}

	/**
	 * @return array{lock: array|null, editable: bool, translated: bool}
	 */
	protected function state(ModelWithContent $model): array
	{
		$version = $model->version('changes');

		$lock = $version->lock('*');
		$lock = $lock->isLocked() ? $lock->toArray() : null;

		return [
			'lock' => $lock,
			'editable' => $model->permissions()->can('update') && $lock === null,
			// Either version can supply the current translation
			'translated' => $version->exists('current') || $model->version('latest')->exists('current'),
		];
	}
}
