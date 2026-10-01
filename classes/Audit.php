<?php

namespace tobimori\Seo;

use Closure;
use Kirby\Cms\App;
use Kirby\Cms\Page;
use Kirby\Cms\Pages;
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
	 * Cascade methods that provide a page-specific value,
	 * anything else (parent, site, defaults) is a fallback shared with other pages
	 */
	public const OWN_SOURCES = ['fields', 'programmatic'];

	protected array|null $result = null;

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
	 * Whether the page has an issue of the given type
	 */
	public function has(Page $page, string $type): bool
	{
		return in_array($type, array_column($this->page($page)['issues'] ?? [], 'type'), true);
	}

	public function result(): array
	{
		return $this->result ??= App::instance()->cache('tobimori.seo.overview')->getOrSet(
			$this->cacheKey(),
			$this->run(...),
			60 * 24
		);
	}

	/**
	 * Changes whenever any of the pages (or the site, which provides fallbacks) changes,
	 * including unsaved changes, as the checks reflect what editors see in the Panel
	 */
	protected function cacheKey(): string
	{
		$kirby = App::instance();
		$modified = fn ($model) => [
			$model->version('latest')->modified('current'),
			$model->version('changes')->modified('current'),
		];

		$fingerprint = [
			$kirby->language()?->code(),
			Seo::option('overview.lengths'),
			$modified($kirby->site()),
		];

		foreach ($this->pages as $page) {
			$fingerprint[] = [$page->id(), $page->status(), $page->intendedTemplate()->name(), ...$modified($page)];
		}

		// bump the version when the format of the results changes
		return 'audit-v3-' . md5(json_encode($fingerprint));
	}

	protected function run(): array
	{
		$pages = [];
		$entries = [];

		foreach ($this->pages as $page) {
			if ($reason = $this->skipped($page)) {
				$pages[$page->id()] = ['skipped' => $reason];
				continue;
			}

			/** @var \tobimori\Seo\Meta $meta */
			$meta = ($this->meta)($page);
			$description = $meta->resolve('metaDescription');

			$entries[$page->id()] = [
				'home' => $page->isHomePage(),
				// the full title, as rendered with the title template
				'title' => static::text($meta->metaTitle()->value()),
				'description' => static::text($description['field']->value()),
				'source' => $description['source'],
			];
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

		// set by editors (or page models) for this page
		if ($source !== 'options') {
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
