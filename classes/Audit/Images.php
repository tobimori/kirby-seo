<?php

namespace tobimori\Seo\Audit;

use Kirby\Cms\App;
use Kirby\Cms\File;
use Kirby\Cms\Page;
use Kirby\Content\Field;
use tobimori\Seo\AltText;

/**
 * Audits accessible image alt-text fields across the site.
 * Images can be stored on pages without SEO fields (e.g. a media library) and used elsewhere,
 * so the audit is not limited to pages in the overview
 *
 * @psalm-type Entry = array{id: string, model: File, fields: list<string>, field: string}
 */
class Images
{
	public const FILTERS = ['missing', 'ai', 'decorative'];
	public const ISSUES = ['missing', 'ai'];

	public const SEVERITY = [
		'missing' => 'negative',
		'ai' => 'notice',
		'decorative' => 'ok',
		'ok' => 'ok'
	];

	/**
	 * @var array<string, Entry>|null
	 */
	protected array|null $entries = null;

	/** @var array<string, list<string>> */
	protected array $fields = [];

	/** @var array<string, string> */
	protected array $states = [];

	/**
	 * @return array<string, Entry>
	 */
	public function entries(): array
	{
		if ($this->entries !== null) {
			return $this->entries;
		}

		$entries = [];
		$site = App::instance()->site();
		// checking permissions per page is slower
		$pages = $site->index(true)->filter(static fn (Page $page) => $page->images()->isNotEmpty() && $page->isListable());

		foreach ([$site, ...$pages] as $parent) {
			foreach ($parent->files() as $file) {
				if ($file->type() !== 'image' || !$file->isListable()) {
					continue;
				}

				foreach ($this->fields($file) as $name) {
					$id = static::id($file, $name);
					$entries[$id] = [
						'id' => $id,
						'model' => $file,
						'fields' => [$name],
						'field' => $name
					];
				}
			}
		}

		return $this->entries = $entries;
	}

	/**
	 * @return Entry|null
	 */
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
	 * @return list<string>
	 */
	public function fields(File $file): array
	{
		$template = $file->template() ?? 'default';

		if (!isset($this->fields[$template])) {
			/** @var array<string, array<string, mixed>> $fields */
			$fields = $file->blueprint()->fields();
			$this->fields[$template] = array_keys(array_filter(
				$fields,
				static fn ($field) => ($field['type'] ?? null) === 'alt-text'
			));
		}

		return $this->fields[$template];
	}

	/**
	 * @param Entry $entry
	 */
	public function altText(array $entry): AltText
	{
		/** @var Field $field */
		$field = $entry['model']->content()->get($entry['field']);
		/** @var string|null $value */
		$value = $field->value();

		return AltText::parse($value);
	}

	/**
	 * Decorative images take precedence over missing or unreviewed text
	 *
	 * @param Entry $entry
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
			default => 'ok'
		};
	}

	/**
	 * @param Entry $entry
	 */
	public function has(array $entry, string $filter): bool
	{
		return $filter === 'issues'
			? in_array($this->state($entry), self::ISSUES, true)
			: $this->state($entry) === $filter;
	}

	/**
	 * @return array<string, int>
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
	 * Cached per role & language until pages or files change (see `clearStats()`), as every overview tab shows them
	 * and they need to read all images otherwise
	 *
	 * @return array{ok: int, notice: int, negative: int}
	 */
	public function stats(): array
	{
		$kirby = App::instance();
		$cache = $kirby->cache('tobimori.seo.overview');
		// alt texts can be translated
		$key = ($kirby->user()?->role()->id() ?? '') . '/' . ($kirby->language()?->code() ?? 'default');
		/** @var array<string, array{ok: int, notice: int, negative: int}> $cached */
		$cached = $cache->get('images-stats') ?? [];

		// entries are loaded anyway in the images view, so its stats are always fresh
		if ($this->entries === null && isset($cached[$key])) {
			return $cached[$key];
		}

		$stats = ['ok' => 0, 'notice' => 0, 'negative' => 0];

		foreach ($this->entries() as $entry) {
			$stats[self::SEVERITY[$this->state($entry)]]++;
		}

		// expires as a fallback for changes outside of Kirby, e.g. uploads via SFTP
		$cache->set('images-stats', [...$cached, $key => $stats], 60);

		return $stats;
	}

	public static function clearStats(): void
	{
		App::instance()->cache('tobimori.seo.overview')->remove('images-stats');
	}
}
