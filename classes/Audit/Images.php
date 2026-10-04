<?php

namespace tobimori\Seo\Audit;

use Kirby\Cms\App;
use Kirby\Cms\File;
use Kirby\Cms\Page;
use tobimori\Seo\AltText;

/**
 * Audits accessible image alt-text fields across the site.
 * Images can be stored on pages without SEO fields (e.g. a media library) and used elsewhere,
 * so the audit is not limited to pages in the overview
 */
class Images
{
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

	protected array $fields = [];

	protected array $states = [];

	/**
	 * @return array<string, array{id: string, model: \Kirby\Cms\File, fields: array<string>, field: string}>
	 */
	public function entries(): array
	{
		if ($this->entries !== null) {
			return $this->entries;
		}

		$this->entries = [];
		$site = App::instance()->site();
		// checking permissions per page is slower
		$pages = $site->index(true)->filter(fn (Page $page) => $page->images()->isNotEmpty() && $page->isListable());

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
	 * Decorative images take precedence over missing or unreviewed text
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

	public function has(array $entry, string $filter): bool
	{
		return $filter === 'issues'
			? in_array($this->state($entry), self::ISSUES, true)
			: $this->state($entry) === $filter;
	}

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
	 * Cached per role until pages or files change (see `clearStats()`), as every overview tab shows them
	 * and they need to read all images otherwise
	 *
	 * @return array{ok: int, notice: int, negative: int}
	 */
	public function stats(): array
	{
		$kirby = App::instance();
		$cache = $kirby->cache('tobimori.seo.overview');
		$role = $kirby->user()?->role()->id() ?? '';
		$cached = $cache->get('images-stats') ?? [];

		// entries are loaded anyway in the images view, so its stats are always fresh
		if ($this->entries === null && isset($cached[$role])) {
			return $cached[$role];
		}

		$stats = ['ok' => 0, 'notice' => 0, 'negative' => 0];

		foreach ($this->entries() as $entry) {
			$stats[self::SEVERITY[$this->state($entry)]]++;
		}

		// expires as a fallback for changes outside of Kirby, e.g. uploads via SFTP
		$cache->set('images-stats', [...$cached, $role => $stats], 60);

		return $stats;
	}

	public static function clearStats(): void
	{
		App::instance()->cache('tobimori.seo.overview')->remove('images-stats');
	}
}
