<?php

namespace tobimori\Seo\Audit;

use Kirby\Cms\App;
use Kirby\Cms\File;
use Kirby\Cms\Page;
use tobimori\Seo\AltText;

/**
 * Alt texts of the images of the site & all pages the current user can see, one entry per `alt-text` field.
 * Not limited to the pages of the overview, as images are often stored on pages without SEO fields
 * (e.g. a media library page) & used elsewhere
 */
class Images
{
	/**
	 * States of alt texts (besides `ok`), `ISSUES` need work
	 */
	public const FILTERS = ['missing', 'ai', 'decorative'];
	public const ISSUES = ['missing', 'ai'];

	public const SEVERITY = [
		'missing' => 'negative',
		'ai' => 'notice',
		'decorative' => 'ok',
		'ok' => 'ok',
	];

	/**
	 * @var array<string, array{id: string, model: \Kirby\Cms\File, fields: array<string>, field: string}>|null
	 */
	protected array|null $entries = null;

	/**
	 * Names of the alt text fields per file template
	 */
	protected array $fields = [];

	/**
	 * States of the entries by their id, see `state()`
	 */
	protected array $states = [];

	/**
	 * Entries by their id, see `id()`
	 *
	 * @return array<string, array{id: string, model: \Kirby\Cms\File, fields: array<string>, field: string}>
	 */
	public function entries(): array
	{
		if ($this->entries !== null) {
			return $this->entries;
		}

		$this->entries = [];
		$site = App::instance()->site();
		$pages = $site->index(true)->filter(fn (Page $page) => $page->isListable());

		foreach ([$site, ...$pages] as $parent) {
			foreach ($parent->files() as $file) {
				if ($file->type() !== 'image' || !$file->isListable()) {
					continue;
				}

				foreach ($this->fields($file) as $name) {
					$id = static::id($file, $name);
					$this->entries[$id] = [
						'id' => $id,
						'model' => $file,
						'fields' => [$name],
						'field' => $name,
					];
				}
			}
		}

		return $this->entries;
	}

	public function find(string $id): array|null
	{
		return $this->entries()[$id] ?? null;
	}

	/**
	 * Entries are per alt text field, as a file might have multiple of them
	 */
	public static function id(File $file, string $field): string
	{
		return "{$file->id()}#{$field}";
	}

	/**
	 * Names of the `alt-text` fields of the file's blueprint
	 *
	 * @return array<string>
	 */
	public function fields(File $file): array
	{
		return $this->fields[$file->template() ?? 'default'] ??= array_keys(array_filter(
			$file->blueprint()->fields(),
			fn ($field) => ($field['type'] ?? null) === 'alt-text'
		));
	}

	public function altText(array $entry): AltText
	{
		return AltText::parse($entry['model']->content()->get($entry['field'])->value());
	}

	/**
	 * `decorative`, `missing`, `ai` (generated & not reviewed yet) or `ok`, in this order:
	 * the text of decorative images isn't used, whoever wrote it
	 */
	public function state(array $entry): string
	{
		if (isset($this->states[$entry['id']])) {
			return $this->states[$entry['id']];
		}

		$alt = $this->altText($entry);

		return $this->states[$entry['id']] = match (true) {
			$alt->isDecorative() => 'decorative',
			$alt->isMissing() => 'missing',
			$alt->isAiGenerated() => 'ai',
			default => 'ok',
		};
	}

	/**
	 * Whether the entry is in the given state (see `FILTERS`), `issues` for any of the `ISSUES`
	 */
	public function has(array $entry, string $filter): bool
	{
		return $filter === 'issues'
			? in_array($this->state($entry), self::ISSUES, true)
			: $this->state($entry) === $filter;
	}

	/**
	 * Number of images per filter
	 */
	public function summary(): array
	{
		$summary = array_fill_keys(self::FILTERS, 0);

		foreach ($this->entries() as $entry) {
			if (isset($summary[$state = $this->state($entry)])) {
				$summary[$state]++;
			}
		}

		return $summary;
	}

	/**
	 * Number of alt texts per severity, like the stats of the other tabs
	 *
	 * @return array{ok: int, notice: int, negative: int}
	 */
	public function stats(): array
	{
		$stats = ['ok' => 0, 'notice' => 0, 'negative' => 0];

		foreach ($this->entries() as $entry) {
			$stats[self::SEVERITY[$this->state($entry)]]++;
		}

		return $stats;
	}
}
