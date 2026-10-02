<?php

namespace tobimori\Seo\Links;

use Closure;
use Kirby\Cms\App;
use Kirby\Exception\Exception;
use Kirby\Toolkit\I18n;
use Kirby\Cms\Page;
use tobimori\Seo\Jobs\CheckLinksJob;
use tobimori\Seo\Seo;

/**
 * Checks the links of all pages in steps, so it fits into requests & queue jobs:
 * each step renders the pages that changed since they were scanned, then checks the
 * external URLs that weren't checked recently, until its time is up.
 *
 * Runs in a queue worker if Kirby Queues is installed, otherwise the Panel
 * runs the steps while the links tab is open.
 */
class Checker
{
	protected Index $index;
	protected array|null $data = null;
	protected array $fingerprints = [];
	protected array|null $lookup = null;

	public function __construct()
	{
		$this->index = new Index();
	}

	/**
	 * Whether the scan runs in a queue worker (instead of the Panel)
	 */
	public static function usesQueue(): bool
	{
		return class_exists('tobimori\Queues\Queues') && Seo::option('links.queue') !== false;
	}

	/**
	 * Starts a scan in the background, e.g. after changing content
	 */
	public static function dispatch(bool $full = false): void
	{
		if (static::usesQueue()) {
			\tobimori\Queues\Queues::push(CheckLinksJob::class, ['full' => $full]);
		}
	}

	/**
	 * Scans all pages & URLs again, also if they didn't change
	 */
	public function invalidate(): void
	{
		$this->change(function (array $data) {
			$data['invalidated'] = time();
			return $data;
		});
	}

	/**
	 * Scans the pages linking to the URL & checks the URL again
	 */
	public function recheck(string $url): void
	{
		$this->change(function (array $data) use ($url) {
			$id = array_search($url, $data['strings'], true);

			foreach ($data['pages'] as $key => $entry) {
				if (in_array($id, [...$entry['links']['content'], ...$entry['links']['layout']], true)) {
					$data['pages'][$key]['fingerprint'] = null;
				}
			}

			unset($data['urls'][strtok($url, '#')]);

			return $data;
		});
	}

	/**
	 * Changes the index between two steps of a running scan
	 */
	protected function change(Closure $change): void
	{
		if (!$this->index->wait(seconds: 10)) {
			throw new Exception(message: I18n::translate('seo.links.busy'));
		}

		try {
			$this->index->write($change($this->index->read()));
		} finally {
			$this->index->unlock();
		}
	}

	/**
	 * Renders pages & checks URLs for the given number of seconds
	 */
	public function step(int $seconds): array
	{
		if (!$this->index->lock($seconds)) {
			return [...$this->progress(), 'running' => true];
		}

		$deadline = microtime(true) + $seconds;
		$this->data = $this->index->read();

		try {
			$targets = $this->prepare();
			$crawler = new Crawler($this->onExit(...));

			foreach ($targets as $key => [$page, $language]) {
				if ($this->isScanned($key)) {
					continue;
				}

				if (microtime(true) >= $deadline) {
					break;
				}

				$this->store($key, $page, $language, $crawler->crawl($key, $page, $language));
			}

			while (microtime(true) < $deadline && ($urls = $this->dueUrls())) {
				$batch = array_slice($urls, 0, max(1, (int)Seo::option('links.concurrency')) * 2);
				$this->data['urls'] = [...$this->data['urls'], ...static::request($batch)];
			}

			$this->index->write($this->data);
		} finally {
			$this->index->unlock();
		}

		return $this->progress();
	}

	/**
	 * How far the scan is, whether pages or URLs are left
	 *
	 * @return array{pages: array{done: int, total: int}, urls: array{done: int, total: int}, done: bool, running: bool, queue: bool}
	 */
	public function progress(): array
	{
		$this->data ??= $this->index->read();
		$targets = $this->prepare();
		$done = count(array_filter(array_keys($targets), $this->isScanned(...)));

		$urls = $this->externalUrls();
		$due = count($this->dueUrls());

		return [
			'pages' => ['done' => $done, 'total' => count($targets)],
			'urls' => ['done' => count($urls) - $due, 'total' => count($urls)],
			'done' => $done === count($targets) && $due === 0,
			'running' => $this->index->isLocked(),
			'queue' => static::usesQueue(),
		];
	}

	/**
	 * Published pages in all languages, by `{language}/{page id}`.
	 * Removes the results of pages that don't exist anymore & marks pages
	 * as changed that link to them, e.g. after changing a slug
	 *
	 * @return array<string, array{0: \Kirby\Cms\Page, 1: string|null}>
	 */
	protected function prepare(): array
	{
		$kirby = App::instance();
		$languages = $kirby->multilang() ? $kirby->languages()->codes() : [null];
		$pages = $kirby->site()->index()->filter(fn (Page $page) => $page->template()->exists());

		// navigation & footer are often part of the site's content
		$site = [$this->data['invalidated']];
		foreach ($languages as $language) {
			$site[] = $kirby->site()->version('latest')->modified($language ?? 'default');
		}

		$targets = [];
		$urls = [];

		foreach ($pages as $page) {
			foreach ($languages as $language) {
				$key = ($language ?? '') . '/' . $page->id();
				$url = $page->url($language);
				$targets[$key] = [$page, $language];
				$urls[Report::normalize($url)] = true;
				$this->fingerprints[$key] = md5(json_encode([
					$site,
					$url,
					$page->status(),
					$page->intendedTemplate()->name(),
					$page->version('latest')->modified($language ?? 'default'),
				]));
			}
		}

		// pages that don't exist anymore (or aren't published, or have a new URL)
		$removed = [];
		foreach ($this->data['pages'] as $entry) {
			if (!isset($urls[$normalized = Report::normalize($entry['url'])])) {
				$removed[$normalized] = true;
			}
		}

		$this->data['pages'] = array_intersect_key($this->data['pages'], $targets);

		if ($removed !== []) {
			$ids = array_filter(
				$this->data['strings'],
				fn ($url) => Report::isInternal($url) && isset($removed[Report::normalize($url)])
			);

			foreach ($this->data['pages'] as $key => $entry) {
				foreach ([...$entry['links']['content'], ...$entry['links']['layout']] as $id) {
					if (isset($ids[$id])) {
						$this->data['pages'][$key]['fingerprint'] = null;
						break;
					}
				}
			}
		}

		return $targets;
	}

	protected function isScanned(string $key): bool
	{
		return ($this->data['pages'][$key]['fingerprint'] ?? null) === $this->fingerprints[$key];
	}

	protected function store(string $key, Page $page, string|null $language, array $result): void
	{
		$this->data['pages'][$key] = [
			'page' => $page->id(),
			'language' => $language,
			'url' => $page->url($language),
			'fingerprint' => $this->fingerprints[$key],
			'status' => $result['status'],
			'location' => $result['location'],
			'error' => $result['error'],
			'links' => array_map($this->intern(...), $result['links']),
			'ids' => $result['ids'],
		];
	}

	/**
	 * A template ended the script while rendering the page, e.g. with `go()`
	 */
	protected function onExit(string $key, string|null $location): void
	{
		[$language, $id] = explode('/', $key, 2);
		$page = App::instance()->page($id);

		if ($this->data !== null && $page !== null) {
			$this->store($key, $page, $language ?: null, [
				'status' => $location ? 302 : 200,
				'location' => $location,
				'error' => null,
				'links' => ['content' => [], 'layout' => []],
				'ids' => [],
			]);
			$this->index->write($this->data);
		}

		$this->index->unlock();
	}

	/**
	 * Index of the URL in the list of linked URLs
	 */
	protected function intern(array $urls): array
	{
		$this->lookup ??= array_flip($this->data['strings']);

		return array_map(function ($url) {
			if (!isset($this->lookup[$url])) {
				$this->lookup[$url] = count($this->data['strings']);
				$this->data['strings'][] = $url;
			}

			return $this->lookup[$url];
		}, $urls);
	}

	/**
	 * All external URLs linked from any page (without fragments)
	 */
	protected function externalUrls(): array
	{
		$urls = [];

		foreach ($this->data['strings'] as $url) {
			if (preg_match('#^https?://#i', $url) && !Report::isInternal($url)) {
				$urls[strtok($url, '#')] = true;
			}
		}

		return array_keys($urls);
	}

	/**
	 * External URLs that haven't been checked recently
	 */
	protected function dueUrls(): array
	{
		$since = max(time() - (int)Seo::option('links.ttl') * 3600, $this->data['invalidated']);

		return array_values(array_filter(
			$this->externalUrls(),
			fn ($url) => ($this->data['urls'][$url]['checked'] ?? 0) < $since
		));
	}

	/**
	 * Checks the URLs in parallel: a HEAD request first, servers that
	 * don't support it get a GET request that stops after the first bytes
	 *
	 * @return array<string, array{code: int, location: string|null, error: string|null, checked: int}>
	 */
	public static function request(array $urls): array
	{
		$results = [];

		if (!function_exists('curl_multi_init')) {
			return array_fill_keys($urls, ['code' => 0, 'location' => null, 'error' => 'curl', 'checked' => time()]);
		}

		foreach (['HEAD', 'GET'] as $method) {
			$multi = curl_multi_init();
			$handles = [];

			foreach ($urls as $url) {
				$handle = curl_init($url);
				curl_setopt_array($handle, [
					CURLOPT_NOBODY => $method === 'HEAD',
					CURLOPT_RETURNTRANSFER => false,
					CURLOPT_HEADER => false,
					CURLOPT_FOLLOWLOCATION => false,
					CURLOPT_CONNECTTIMEOUT => 5,
					CURLOPT_TIMEOUT => (int)Seo::option('links.timeout'),
					CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; Kirby SEO link checker; +' . App::instance()->url() . ')',
					CURLOPT_HTTPHEADER => ['Accept: text/html,application/xhtml+xml,*/*;q=0.8'],
					// only the status is needed: stop at the first bytes of the body
					CURLOPT_WRITEFUNCTION => fn () => 0,
				]);
				curl_multi_add_handle($multi, $handle);
				$handles[$url] = $handle;
			}

			do {
				$status = curl_multi_exec($multi, $running);
				if ($running) {
					curl_multi_select($multi);
				}
			} while ($running && $status === CURLM_OK);

			$retry = [];

			foreach ($handles as $url => $handle) {
				$code = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
				$error = curl_errno($handle);

				$results[$url] = [
					'code' => $code,
					'location' => curl_getinfo($handle, CURLINFO_REDIRECT_URL) ?: null,
					// aborting the body is expected, everything else (DNS, timeout, …) isn't
					'error' => $code === 0 && $error !== 0 ? match ($error) {
						CURLE_OPERATION_TIMEDOUT => 'timeout',
						CURLE_COULDNT_RESOLVE_HOST => 'dns',
						default => 'connection',
					} : null,
					'checked' => time(),
				];

				// servers that don't (properly) support HEAD requests
				if ($method === 'HEAD' && $code >= 400) {
					$retry[] = $url;
				}

				curl_multi_remove_handle($multi, $handle);
			}

			curl_multi_close($multi);

			if (($urls = $retry) === []) {
				break;
			}
		}

		return $results;
	}
}
