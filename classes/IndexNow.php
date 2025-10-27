<?php

namespace tobimori\Seo;

use Kirby\Cms\App;
use Kirby\Cms\Page;
use Kirby\Http\Remote;
use Kirby\Toolkit\Str;

class IndexNow
{
	protected static string|null $key = null;

	protected Page $page;
	protected array $urls = [];
	protected bool $collected = false;

	public function __construct(Page $page)
	{
		$this->page = $page;

		// always add the current page if it's indexable
		if ($this->isIndexable($page)) {
			$this->urls[] = $page->url();
		}
	}

	/**
	 * Collect URLs to be indexed based on rules
	 */
	public function collect(): self
	{
		if ($this->collected) {
			return $this;
		}

		$rules = Seo::option('indexnow.rules') ?? [];

		foreach ($rules as $pattern => $invalidations) {
			if (!$this->matchesPattern($pattern)) {
				continue;
			}

			$this->collectFromRule($invalidations);
		}

		$this->urls = array_unique($this->urls);
		$this->collected = true;

		return $this;
	}

	/**
	 * Get collected urls
	 */
	public function urls(): array
	{
		if (!$this->collected) {
			$this->collect();
		}

		return $this->urls;
	}

	/**
	 * Send the collected urls
	 */
	public function request(): bool
	{
		if (!$this->collected) {
			$this->collect();
		}

		return static::send($this->urls);
	}

	/**
	 * Static method to send urls to indexnow api
	 */
	public static function send(array $urls): bool
	{
		if (!Seo::option('indexnow.enabled') || empty($urls)) {
			return false;
		}

		$firstUrl = $urls[0];
		$parsedUrl = parse_url($firstUrl);
		$host = $parsedUrl['host'];
		$scheme = $parsedUrl['scheme'] ?? 'https';
		$path = $parsedUrl['path'] ?? '';

		// don't send requests for local development environments
		if (App::instance()->environment()->isLocal()) {
			return false;
		}

		// get base path (everything before the page path)
		$basePath = '';
		if ($path && $path !== '/') {
			// find the base path by comparing with site url
			$siteUrl = $this->page->site()->url();
			$siteParsed = parse_url($siteUrl);
			$basePath = $siteParsed['path'] ?? '';
		}

		$searchEngine = Seo::option('indexnow.searchEngine');
		$searchEngine = rtrim($searchEngine, '/');
		if (!str_contains($searchEngine, '/indexnow')) {
			$searchEngine .= '/indexnow';
		}

		$domainUrls = array_filter($urls, fn ($url) => parse_url($url, PHP_URL_HOST) === $host);

		// split into batches of 10,000 (IndexNow limit)
		$batches = array_chunk(array_values(array_unique($domainUrls)), 10000);
		$allSuccessful = true;
		$key = static::key();

		foreach ($batches as $batch) {
			try {
				$response = Remote::post($searchEngine, [
					'headers' => [
						'Content-Type' => 'application/json; charset=utf-8',
						'User-Agent' => Seo::userAgent()
					],
					'data' => json_encode([
						'host' => $host,
						'key' => $key,
						'keyLocation' => "{$scheme}://{$host}{$basePath}/indexnow-{$key}.txt",
						'urlList' => $batch
					])
				]);

				if ($response->code() > 299) {
					$allSuccessful = false;
				}
			} catch (\Exception $e) {
				$allSuccessful = false;
			}
		}

		return $allSuccessful;
	}

	/**
	 * Get or generate the indexnow key
	 * Stored in cache so it persists across requests
	 */
	public static function key(): string
	{
		return static::$key ??= App::instance()->cache('tobimori.seo.indexnow')->getOrSet('key', fn () => Str::random(32, 'hexLower'), 0);
	}

	/**
	 * Check if a provided key matches the stored key
	 * Used by the route to verify ownership
	 */
	public static function verifyKey(string $providedKey): bool
	{
		return $providedKey === static::key();
	}

	/**
	 * Check if page matches a pattern (url glob/regex or template)
	 */
	protected function matchesPattern(string $pattern): bool
	{
		if ($pattern === '*') {
			return true;
		}

		// url pattern
		if (str_contains($pattern, '/')) {
			return $this->matchesUrlPattern($pattern, $this->page->url());
		}

		// template pattern
		return $this->page->intendedTemplate()->name() === $pattern;
	}

	/**
	 * Match url pattern (glob style)
	 */
	protected function matchesUrlPattern(string $pattern, string $url): bool
	{
		// convert glob to regex
		$pattern = str_replace(
			['*', '?', '[', ']'],
			['.*', '.', '\[', '\]'],
			$pattern
		);

		return preg_match('#^' . $pattern . '$#', parse_url($url, PHP_URL_PATH));
	}

	/**
	 * Collect urls based on invalidation rules
	 */
	protected function collectFromRule(array $rule): void
	{
		// parent(s)
		if (isset($rule['parent'])) {
			$this->collectParents($rule['parent']);
		}

		// children
		if (isset($rule['children'])) {
			$this->collectChildren($rule['children']);
		}

		// siblings
		if (isset($rule['siblings']) && $rule['siblings'] === true) {
			$this->collectSiblings();
		}

		// specific urls
		if (isset($rule['urls'])) {
			foreach ($rule['urls'] as $url) {
				$this->urls[] = url($url);
			}
		}

		// pages by template
		if (isset($rule['templates'])) {
			$this->collectByTemplates($rule['templates']);
		}
	}

	/**
	 * Collect parent urls
	 */
	protected function collectParents($levels): void
	{
		$parent = $this->page->parent();
		$count = is_bool($levels) ? 1 : $levels;
		$language = App::instance()->language();

		while ($parent && $count > 0) {
			if ($this->isIndexable($parent)) {
				$this->urls[] = $parent->url($language?->code());
			}
			$parent = $parent->parent();
			$count--;
		}
	}

	/**
	 * Collect children urls
	 */
	protected function collectChildren($depth): void
	{
		$maxDepth = is_bool($depth) ? null : $depth;
		$language = App::instance()->language();

		$collectRecursive = function ($page, $currentDepth = 0) use (&$collectRecursive, $maxDepth, $language) {
			if ($maxDepth !== null && $currentDepth >= $maxDepth) {
				return;
			}

			foreach ($page->children() as $child) {
				if ($this->isIndexable($child)) {
					$this->urls[] = $child->url($language?->code());
				}
				$collectRecursive($child, $currentDepth + 1);
			}
		};

		$collectRecursive($this->page);
	}

	/**
	 * Collect sibling urls
	 */
	protected function collectSiblings(): void
	{
		if (!$this->page->parent()) {
			return;
		}

		$language = App::instance()->language();

		foreach ($this->page->siblings() as $sibling) {
			if ($this->isIndexable($sibling)) {
				$this->urls[] = $sibling->url($language?->code());
			}
		}
	}

	/**
	 * Collect urls by template names
	 */
	protected function collectByTemplates(array $templates): void
	{
		$language = App::instance()->language();

		$pages = $this->page->site()->index()
			->filterBy('intendedTemplate', 'in', $templates)
			->filter(fn ($page) => $this->isIndexable($page));

		foreach ($pages as $page) {
			$this->urls[] = $page->url($language?->code());
		}
	}

	/**
	 * Check if a page is indexable (robots allow + listed)
	 */
	protected function isIndexable(Page $page): bool
	{
		return $page->isListed()
			&& $page->robots() !== 'noindex'
			&& $page->robots() !== 'none';
	}
}
