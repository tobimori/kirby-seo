<?php

namespace tobimori\Seo\Audit;

use Kirby\Cms\App;
use Kirby\Cms\Page;
use Kirby\Cms\Site;
use Kirby\Data\Json;
use Kirby\Toolkit\Str;
use tobimori\Seo\Seo;

/**
 * Audits page titles and descriptions for SEO issues across a page collection
 *
 * @psalm-type Checked = array{values: array<string, string>, home: bool, title: string, description: string, source: string|null}
 * @psalm-type Entry = array{fingerprint?: string, skipped: string, values: array<string, string>}|array{fingerprint?: string, values: array<string, string>, home: bool, title: string, description: string, source: string|null}
 * @psalm-type Issue = array{type: string, severity?: string, group?: array-key, count?: int, source?: string|null, variant?: string, length?: int, min?: int, max?: int}
 * @psalm-type Group = array{kind: string, text: string, pages: list<array-key>}
 * @psalm-type PageResult = array{issues: list<Issue>}|array{skipped: string}
 * @psalm-type Result = array{pages: array<array-key, PageResult>, summary: array<string, int>, groups: array<array-key, Group>}
 */
class Pages
{
	/**
	 * Types of issues, roughly ordered by severity
	 */
	public const TYPES = [
		'descriptionMissing',
		'descriptionDuplicate',
		'titleDuplicate',
		'descriptionFallback',
		'titleLength',
		'descriptionLength'
	];

	public const SEVERITY = [
		'descriptionMissing' => 'negative',
		'descriptionDuplicate' => 'negative',
		'titleDuplicate' => 'negative',
		// Fallback descriptions need review even when no duplicate is found
		'descriptionFallback' => 'negative',
		'titleLength' => 'notice',
		'descriptionLength' => 'notice'
	];

	public const KINDS = ['title', 'description'];

	/**
	 * Sources treated as page-specific when classifying descriptions
	 */
	public const OWN_SOURCES = ['fields', 'programmatic'];

	public const VALUES = ['metaTitle', 'metaDescription', 'ogDescription'];

	/** @var array<array-key, Entry>|null */
	protected array|null $entries = null;

	/** @var Result|null */
	protected array|null $result = null;

	/** @var array<array-key, string> */
	protected array $fingerprints = [];

	public function __construct(protected \Kirby\Cms\Pages $pages)
	{
	}

	/**
	 * Checks of a single page: `['issues' => [...]]`, or `['skipped' => 'reason']`
	 * for untranslated pages
	 *
	 * @return PageResult|null
	 */
	public function page(Page $page): array|null
	{
		return $this->result()['pages'][$page->id()] ?? null;
	}

	/**
	 * @return array<string, int>
	 */
	public function summary(): array
	{
		return $this->result()['summary'];
	}

	/**
	 * Pages sharing the same title or description, by the group's hash
	 *
	 * @return Group|null
	 */
	public function group(string $hash): array|null
	{
		return $this->result()['groups'][$hash] ?? null;
	}

	/**
	 * Returns a cached plain-text title or metadata value for search and sorting.
	 * Returns `null` for unsupported keys or pages outside the audit
	 */
	public function value(Page $page, string $key): string|null
	{
		return $this->entries()[$page->id()]['values'][$key] ?? null;
	}

	/**
	 * Matches an issue type or any issue of a given kind (`title` or `description`)
	 */
	public function has(Page $page, string $type): bool
	{
		foreach (array_column($this->page($page)['issues'] ?? [], 'type') as $issue) {
			if ($issue === $type || in_array($type, self::KINDS, true) && str_starts_with($issue, $type)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Number of checked pages & their distribution per kind (title/description):
	 * pages count once, by their most severe issue of that kind
	 *
	 * @return array{checked: int, skipped: int, title: array{ok: int, notice: int, negative: int}, description: array{ok: int, notice: int, negative: int}}
	 */
	public function stats(): array
	{
		$distribution = ['ok' => 0, 'notice' => 0, 'negative' => 0];
		$stats = ['checked' => 0, 'skipped' => 0];

		foreach (self::KINDS as $kind) {
			$stats[$kind] = $distribution;
		}

		foreach ($this->result()['pages'] as $page) {
			if (!isset($page['issues'])) {
				$stats['skipped']++;
				continue;
			}

			$stats['checked']++;

			foreach (self::KINDS as $kind) {
				$severity = 'ok';

				foreach ($page['issues'] as $issue) {
					if (!str_starts_with($issue['type'], $kind)) {
						continue;
					}

					if (self::SEVERITY[$issue['type']] === 'negative') {
						$severity = 'negative';
					} elseif ($severity === 'ok') {
						$severity = 'notice';
					}
				}

				$stats[$kind][$severity]++;
			}
		}

		return $stats;
	}

	/**
	 * @return Result
	 */
	public function result(): array
	{
		return $this->result ??= $this->evaluate($this->entries());
	}

	/**
	 * Loads per-page audit inputs, resolving metadata only for missing or changed entries.
	 * Duplicate groups and issues are evaluated separately for the current collection
	 *
	 * @return array<array-key, Entry>
	 */
	protected function entries(): array
	{
		if ($this->entries !== null) {
			return $this->entries;
		}

		$kirby = App::instance();
		$cache = $kirby->cache('tobimori.seo.overview');

		$key = 'audit-' . md5(Json::encode([$kirby->language()?->code(), $this->modified($kirby->site()), Seo::auditOptions()]));
		/** @var array<array-key, Entry> $cached */
		$cached = $cache->get($key) ?? [];
		$entries = [];
		$changed = false;

		foreach ($this->pages as $page) {
			$fingerprint = $this->fingerprint($page);

			if (($cached[$page->id()]['fingerprint'] ?? null) === $fingerprint) {
				$entries[$page->id()] = $cached[$page->id()];
				continue;
			}

			$entries[$page->id()] = ['fingerprint' => $fingerprint, ...$this->entry($page)];
			$changed = true;
		}

		if ($changed) {
			$cache->set($key, [...$cached, ...$entries], 60 * 24);
		}

		return $this->entries = $entries;
	}

	protected function fingerprint(Page $page): string
	{
		return $this->fingerprints[$page->id()] ??= md5(Json::encode([
			$page->status(),
			$page->intendedTemplate()->name(),
			$this->modified($page),
			($parent = $page->parent()) ? $this->fingerprint($parent) : null
		]));
	}

	/**
	 * @return list<int|null>
	 */
	protected function modified(Page|Site $model): array
	{
		return [
			$model->version('latest')->modified('current'),
			$model->version('changes')->modified('current')
		];
	}

	/**
	 * @return Entry
	 */
	protected function entry(Page $page): array
	{
		// Do not retain Meta instances while filling the cache for the entire site
		$meta = new (Seo::option('components.meta'))($page);
		$values = ['title' => $page->title()->toString()];

		foreach (self::VALUES as $key) {
			$values[$key] = $meta->resolve($key)['field']->toString();
		}

		// skipped pages are listed (& searched) as well
		if ($reason = $this->skipped($page)) {
			return ['skipped' => $reason, 'values' => $values];
		}

		$description = $meta->resolve('metaDescription');

		return [
			'values' => $values,
			'home' => $page->isHomePage(),
			// the full title, as rendered with the title template
			'title' => static::text($meta->metaTitle()->value()),
			'description' => static::text($description['field']->value()),
			'source' => $description['source']
		];
	}

	/**
	 * @param array<array-key, Entry> $entries
	 * @return Result
	 */
	protected function evaluate(array $entries): array
	{
		$pages = [];
		$checked = [];

		foreach ($entries as $id => $entry) {
			if (isset($entry['skipped'])) {
				$pages[$id] = ['skipped' => $entry['skipped']];
			} else {
				// Mago merges the shapes of the union, so it can't narrow them
				/** @var Checked $entry */
				$checked[$id] = $entry;
			}
		}

		$entries = $checked;

		$groups = $this->groups($entries);

		/** @var array<array-key, array<string, array-key>> $memberOf */
		$memberOf = [];
		foreach ($groups as $hash => $group) {
			foreach ($group['pages'] as $id) {
				$memberOf[$id][$group['kind']] = $hash;
			}
		}

		$summary = array_fill_keys(self::TYPES, 0);

		foreach ($entries as $id => $entry) {
			$issues = $this->issues($entry, $memberOf[$id] ?? [], $groups);

			foreach (array_unique(array_column($issues, 'type')) as $type) {
				$summary[$type]++;
			}

			$pages[$id] = ['issues' => $issues];
		}

		return [
			'pages' => $pages,
			'summary' => $summary,
			'groups' => $groups
		];
	}

	/**
	 * @param Checked $entry
	 * @param array<string, array-key> $memberOf
	 * @param array<array-key, Group> $groups
	 * @return list<Issue>
	 */
	protected function issues(array $entry, array $memberOf, array $groups): array
	{
		$issues = [];

		if ($hash = $memberOf['title'] ?? null) {
			$issues[] = ['type' => 'titleDuplicate', 'group' => $hash, 'count' => count($groups[$hash]['pages'])];
		}

		if ($length = $this->length('title', $entry['title'])) {
			$issues[] = ['type' => 'titleLength', ...$length];
		}

		if ($entry['description'] === '') {
			$issues[] = ['type' => 'descriptionMissing'];
		} elseif (!in_array($entry['source'], self::OWN_SOURCES, true)) {
			// a description shared via fallbacks is fine for the home page only
			// (https://developers.google.com/search/docs/appearance/snippet)
			if (!$entry['home']) {
				$issues[] = ['type' => 'descriptionFallback', 'source' => $entry['source']];
			}
		} else {
			if ($hash = $memberOf['description'] ?? null) {
				$issues[] = ['type' => 'descriptionDuplicate', 'group' => $hash, 'count' => count($groups[$hash]['pages'])];
			}

			if ($length = $this->length('description', $entry['description'])) {
				$issues[] = ['type' => 'descriptionLength', ...$length];
			}
		}

		$order = array_flip(self::TYPES);
		usort($issues, static fn ($a, $b) => $order[$a['type']] <=> $order[$b['type']]);

		return array_map(static fn ($issue) => [...$issue, 'severity' => self::SEVERITY[$issue['type']]], $issues);
	}

	/**
	 * Groups of pages that share the same title, or the same description of their own
	 * (descriptions shared via fallbacks are reported as such instead)
	 *
	 * @param array<array-key, Checked> $entries
	 * @return array<array-key, Group>
	 */
	protected function groups(array $entries): array
	{
		/** @var array<string, array<array-key, list<array{array-key, string}>>> $candidates */
		$candidates = [];

		foreach ($entries as $id => $entry) {
			if ($entry['title'] !== '') {
				$candidates['title'][static::normalize($entry['title'])][] = [$id, $entry['title']];
			}

			if ($entry['description'] !== '' && in_array($entry['source'], self::OWN_SOURCES, true)) {
				$candidates['description'][static::normalize($entry['description'])][] = [$id, $entry['description']];
			}
		}

		$groups = [];

		foreach ($candidates as $kind => $values) {
			foreach ($values as $normalized => $members) {
				if (count($members) > 1) {
					$groups[substr(md5($kind . ':' . $normalized), 0, 12)] = [
						'kind' => $kind,
						'text' => $members[0][1],
						'pages' => array_map(static fn ($member) => $member[0], $members)
					];
				}
			}
		}

		return $groups;
	}

	/**
	 * Length issue for the given kind (`title` or `description`), if any
	 *
	 * @return array{variant: string, length: int, min: int, max: int}|null
	 */
	protected function length(string $kind, string $text): array|null
	{
		/** @var array{int, int} $lengths */
		$lengths = Seo::option("overview.lengths.{$kind}") ?? [0, PHP_INT_MAX];
		[$min, $max] = $lengths;
		$length = Str::length($text);

		return match (true) {
			$text === '' => null,
			$length > $max => ['variant' => 'long', 'length' => $length, 'min' => $min, 'max' => $max],
			$length < $min => ['variant' => 'short', 'length' => $length, 'min' => $min, 'max' => $max],
			default => null
		};
	}

	/**
	 * Pages without a translation show the content of the default language
	 */
	protected function skipped(Page $page): string|null
	{
		return !$page->version('latest')->exists('current') && !$page->version('changes')->exists('current')
			? 'untranslated'
			: null;
	}

	protected static function text(mixed $value): string
	{
		return trim((string)preg_replace('/\s+/u', ' ', (string)$value));
	}

	protected static function normalize(string $text): string
	{
		return Str::lower($text);
	}
}
