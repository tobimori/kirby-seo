<?php

namespace tobimori\Seo;

use Closure;
use Kirby\Cms\App;
use Kirby\Cms\Page;
use Kirby\Cms\Pages;
use Kirby\Cms\Site;
use Kirby\Toolkit\Str;

/**
 * Checks the metadata of pages as search engines see it, e.g. for duplicate titles
 * & descriptions. Duplicates can only be detected across all pages, so the checks always
 * run for all given pages, the results are cached until any of them changes.
 */
class Audit
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
		'descriptionLength',
	];

	/**
	 * Severity of the issue types: `negative` needs fixing, `notice` is acceptable.
	 * A fallback description is shared with other pages, which makes it a duplicate
	 */
	public const SEVERITY = [
		'descriptionMissing' => 'negative',
		'descriptionDuplicate' => 'negative',
		'titleDuplicate' => 'negative',
		'descriptionFallback' => 'negative',
		'titleLength' => 'notice',
		'descriptionLength' => 'notice',
	];

	/**
	 * Issue types are prefixed with the kind of value they're about
	 */
	public const KINDS = ['title', 'description'];

	/**
	 * Cascade methods that provide a page-specific value,
	 * anything else (parent, site, defaults) is a fallback shared with other pages
	 */
	public const OWN_SOURCES = ['fields', 'programmatic'];

	protected array|null $result = null;
	protected array $fingerprints = [];

	/**
	 * @param \Closure(\Kirby\Cms\Page): \tobimori\Seo\Meta $meta
	 */
	public function __construct(
		protected Pages $pages,
		protected Closure $meta
	) {
	}

	/**
	 * Checks of a single page: `['issues' => [...]]`, or `['skipped' => 'reason']`
	 * for pages that aren't checked (drafts, untranslated pages, pages hidden from search engines)
	 */
	public function page(Page $page): array|null
	{
		return $this->result()['pages'][$page->id()] ?? null;
	}

	/**
	 * Number of pages per issue type
	 */
	public function summary(): array
	{
		return $this->result()['summary'];
	}

	/**
	 * Pages sharing the same title or description, by the group's hash
	 *
	 * @return array{kind: string, text: string, pages: array<string>}|null
	 */
	public function group(string $hash): array|null
	{
		return $this->result()['groups'][$hash] ?? null;
	}

	/**
	 * Whether the page has an issue of the given type, or of the given kind (any title/description issue)
	 */
	public function has(Page $page, string $type): bool
	{
		foreach (array_column($this->page($page)['issues'] ?? [], 'type') as $issue) {
			if ($issue === $type || (in_array($type, self::KINDS, true) && str_starts_with($issue, $type))) {
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
					if (str_starts_with($issue['type'], $kind)) {
						$severity = self::SEVERITY[$issue['type']] === 'negative' ? 'negative' : ($severity === 'negative' ? 'negative' : 'notice');
					}
				}

				$stats[$kind][$severity]++;
			}
		}

		return $stats;
	}

	public function result(): array
	{
		return $this->result ??= $this->evaluate($this->entries());
	}

	protected function entries(): array
	{
		$kirby = App::instance();
		$cache = $kirby->cache('tobimori.seo.overview');

		$key = 'audit-v6-' . md5(json_encode([$kirby->language()?->code(), $this->modified($kirby->site()), $this->options()]));
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

		return $entries;
	}

	/**
	 * Options the results depend on, e.g. after enabling `debug` (which sets `robots.index` to `false`).
	 * Closures (e.g. most defaults) are encoded as empty objects, changes of their code aren't detected
	 */
	protected function options(): array
	{
		return [
			Seo::option('robots.enabled'),
			Seo::option('robots.index'),
			Seo::option('robots.followPageStatus'),
			Seo::option('cascade'),
			Seo::option('default'),
		];
	}

	protected function fingerprint(Page $page): string
	{
		return $this->fingerprints[$page->id()] ??= md5(json_encode([
			$page->status(),
			$page->intendedTemplate()->name(),
			$this->modified($page),
			$page->parent() ? $this->fingerprint($page->parent()) : null,
		]));
	}

	protected function modified(Page|Site $model): array
	{
		return [
			$model->version('latest')->modified('current'),
			$model->version('changes')->modified('current'),
		];
	}

	protected function entry(Page $page): array
	{
		if ($reason = $this->skipped($page)) {
			return ['skipped' => $reason];
		}

		/** @var \tobimori\Seo\Meta $meta */
		$meta = ($this->meta)($page);
		$description = $meta->resolve('metaDescription');

		return [
			'home' => $page->isHomePage(),
			// the full title, as rendered with the title template
			'title' => static::text($meta->metaTitle()->value()),
			'description' => static::text($description['field']->value()),
			'source' => $description['source'],
		];
	}

	protected function evaluate(array $entries): array
	{
		$pages = [];

		foreach ($entries as $id => $entry) {
			if (isset($entry['skipped'])) {
				$pages[$id] = ['skipped' => $entry['skipped']];
				unset($entries[$id]);
			}
		}

		$groups = $this->groups($entries);

		// group hashes by page id & kind (title/description)
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
			'groups' => $groups,
		];
	}

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

		// most severe first
		usort($issues, fn ($a, $b) => array_search($a['type'], self::TYPES) <=> array_search($b['type'], self::TYPES));

		return $issues;
	}

	/**
	 * Groups of pages that share the same title, or the same description of their own
	 * (descriptions shared via fallbacks are reported as such instead)
	 */
	protected function groups(array $entries): array
	{
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
						'pages' => array_column($members, 0),
					];
				}
			}
		}

		return $groups;
	}

	/**
	 * Length issue for the given kind (`title` or `description`), if any
	 */
	protected function length(string $kind, string $text): array|null
	{
		[$min, $max] = Seo::option("overview.lengths.{$kind}") ?? [0, PHP_INT_MAX];
		$length = Str::length($text);

		return match (true) {
			$text === '' => null,
			$length > $max => ['variant' => 'long', 'length' => $length, 'min' => $min, 'max' => $max],
			$length < $min => ['variant' => 'short', 'length' => $length, 'min' => $min, 'max' => $max],
			default => null,
		};
	}

	/**
	 * Why the page isn't checked, if it isn't
	 */
	protected function skipped(Page $page): string|null
	{
		return match (true) {
			$page->isDraft() => 'draft',
			// pages without a translation show the content of the default language
			!$page->version('latest')->exists('current') && !$page->version('changes')->exists('current') => 'untranslated',
			!$this->isIndexable($page) => 'noindex',
			default => null,
		};
	}

	/**
	 * Whether search engines may index the page. If the whole site is set to `noindex`
	 * (e.g. on staging environments), pages are checked as if it wasn't
	 */
	protected function isIndexable(Page $page): bool
	{
		if (!Seo::option('robots.enabled')) {
			return true;
		}

		/** @var \tobimori\Seo\Meta $meta */
		$meta = ($this->meta)($page);

		if (Seo::option('robots.index')) {
			return !Str::contains($meta->robots(), 'noindex');
		}

		['field' => $field, 'source' => $source] = $meta->resolve('robotsIndex');

		// set by editors (or page models) for this page, `null` if the default is `false`
		if ($source !== null && $source !== 'options') {
			return $field->toBool();
		}

		// same as the default, without the site-wide switch
		return Seo::option('robots.followPageStatus') ? $page->isListed() : true;
	}

	/**
	 * Plain text, as shown in search results
	 */
	public static function text(mixed $value): string
	{
		return trim(preg_replace('/\s+/u', ' ', Str::unhtml((string)$value)));
	}

	/**
	 * Values that only differ in case or whitespace count as duplicates
	 */
	protected static function normalize(string $text): string
	{
		return Str::lower($text);
	}
}
