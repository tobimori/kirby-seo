<?php

namespace tobimori\Seo\Audit\Links;

use Closure;
use Kirby\Cms\App;
use Kirby\Cms\Page;
use Kirby\Exception\Exception;
use Kirby\Toolkit\I18n;
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

	/**
	 * Comparable URLs of the pages to scan (as keys), see `targets()`
	 */
	protected array $urls = [];
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
	 * Starts a scan in the background: after changing content, changes within the job's
	 * batch window are checked together, scans started by users (`$now`) start right away
	 */
	public static function dispatch(bool $full = false, bool $now = false): void
	{
		if (!static::usesQueue()) {
			return;
		}

		if ($now) {
			\tobimori\Queues\Queues::later(0, CheckLinksJob::class, ['full' => $full]);
		} else {
			\tobimori\Queues\Queues::push(CheckLinksJob::class, ['full' => $full]);
		}
	}

	/**
	 * Schedules the regular check of all pages & external URLs (`links.schedule`) for the queue worker.
	 * Queues stores schedules, so schedules of previous values of the option are removed
	 */
	public static function schedule(): void
	{
		$scheduler = \tobimori\Queues\Queues::scheduler();
		$expression = static::usesQueue() ? Seo::option('links.schedule') : null;
		$type = (new CheckLinksJob())->type();
		$exists = false;

		foreach ($scheduler->all() as $id => $entry) {
			if ($entry['job'] !== $type) {
				continue;
			}

			if ($entry['expression'] === $expression && !$exists) {
				$exists = true;
				continue;
			}

			$scheduler->unschedule($id);
		}

		if ($expression && !$exists) {
			$scheduler->schedule($expression, CheckLinksJob::class, ['full' => true]);
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
				if (in_array($id, $entry['links'], true)) {
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
		if (!$this->index->wait()) {
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
		if (!$this->index->lock()) {
			return [...$this->progress(), 'running' => true];
		}

		$deadline = microtime(true) + $seconds;
		$this->data = $this->index->read();
		// Index compaction can renumber URL IDs between steps
		$this->lookup = null;

		try {
			$targets = $this->prepare();
			$crawler = new Crawler($this->onExit(...));

			$pending = array_filter($targets, fn ($key) => !$this->isScanned($key), ARRAY_FILTER_USE_KEY);
			$http = Crawler::usesHttp();

			// requests run in parallel, rendering in this process one page after the other
			foreach (array_chunk($pending, $http ? max(1, (int)Seo::option('links.concurrency')) : 1, true) as $batch) {
				if (microtime(true) >= $deadline) {
					break;
				}

				$results = [];

				if ($http) {
					$results = Crawler::fetch(array_map(fn ($target) => $target[0]->url($target[1]), $batch));
				} else {
					foreach ($batch as $key => [$page, $language]) {
						$results[$key] = $crawler->render($key, $page, $language);
					}
				}

				foreach ($results as $key => $result) {
					[$page, $language] = $targets[$key];
					$this->store($key, $page, $language, $result);
				}
			}

			while (microtime(true) < $deadline && ($urls = $this->dueUrls(Index::external($this->data), $this->data['invalidated']))) {
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
		// a step has the index in memory already, otherwise only its summary is read
		$state = $this->data !== null ? Index::summarize($this->data) : $this->index->state();
		$targets = $this->targets($state['invalidated']);

		// pages that don't exist anymore (or have a new URL) mark the pages linking
		// to them as changed (see `prepare()`), which needs the links of all pages
		foreach ($state['pages'] as [, $url]) {
			if (!isset($this->urls[$url])) {
				$this->data ??= $this->index->read();
				$this->prepare();
				$state = Index::summarize($this->data);
				break;
			}
		}

		$done = count(array_filter(
			array_keys($targets),
			fn ($key) => ($state['pages'][$key][0] ?? null) === $this->fingerprints[$key]
		));
		$due = count($this->dueUrls($state['external'], $state['invalidated']));

		return [
			'pages' => ['done' => $done, 'total' => count($targets)],
			'urls' => ['done' => count($state['external']) - $due, 'total' => count($state['external'])],
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
		$targets = $this->targets($this->data['invalidated']);

		// pages that don't exist anymore (or aren't published, or have a new URL)
		$removed = [];
		foreach ($this->data['pages'] as $entry) {
			if (!isset($this->urls[$normalized = Report::normalize($entry['url'])])) {
				$removed[$normalized] = true;
			}
		}

		$this->data['pages'] = array_intersect_key($this->data['pages'], $targets);
		$this->data['targets'] = array_keys($this->urls);

		if ($removed !== []) {
			$ids = array_filter(
				$this->data['strings'],
				fn ($url) => Report::isInternal($url) && isset($removed[Report::normalize($url)])
			);

			foreach ($this->data['pages'] as $key => $entry) {
				foreach ($entry['links'] as $id) {
					if (isset($ids[$id])) {
						$this->data['pages'][$key]['fingerprint'] = null;
						break;
					}
				}
			}
		}

		return $targets;
	}

	/**
	 * Published pages in all languages, by `{language}/{page id}`, with their fingerprints
	 * (pages are scanned again if it changes) & their comparable URLs (see `$urls`)
	 *
	 * @return array<string, array{0: \Kirby\Cms\Page, 1: string|null}>
	 */
	protected function targets(int $invalidated): array
	{
		$kirby = App::instance();
		$languages = $kirby->multilang() ? $kirby->languages()->codes() : [null];
		$pages = $kirby->site()->index()->filter(fn (Page $page) => $page->template()->exists());

		// navigation & footer are often part of the site's content
		$site = [$invalidated];
		foreach ($languages as $language) {
			$site[] = $kirby->site()->version('latest')->modified($language ?? 'default');
		}

		$targets = [];
		$this->urls = [];

		foreach ($pages as $page) {
			foreach ($languages as $language) {
				$key = ($language ?? '') . '/' . $page->id();
				$url = $page->url($language);
				$targets[$key] = [$page, $language];
				$this->urls[Report::normalize($url)] = true;
				$this->fingerprints[$key] = md5(json_encode([
					$site,
					$url,
					$page->status(),
					$page->intendedTemplate()->name(),
					$page->version('latest')->modified($language ?? 'default'),
				]));
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
			'links' => $this->intern($result['links']),
			'ids' => $result['ids'],
		];
	}

	/**
	 * A template ended the script while rendering the page, e.g. with `go()`
	 */
	protected function onExit(string $key, int $code, string|null $location): void
	{
		[$language, $id] = explode('/', $key, 2);
		$page = App::instance()->page($id);

		if ($this->data !== null && $page !== null) {
			$this->store($key, $page, $language ?: null, [
				'status' => $location && $code < 300 ? 302 : $code,
				'location' => $location,
				'error' => null,
				'links' => [],
				'ids' => [],
			]);
			$this->index->write($this->data);
		}

		$this->index->unlock();

		// the script ends, e.g. a queue worker rendering pages in its own process: the next job continues
		if (PHP_SAPI === 'cli') {
			static::dispatch(now: true);
		}
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
	 * External URLs that haven't been checked recently
	 *
	 * @param array<string, int> $external Check times by URL, see `Index::external()`
	 */
	protected function dueUrls(array $external, int $invalidated): array
	{
		$since = max(time() - (int)Seo::option('links.ttl') * 3600, $invalidated);

		return array_keys(array_filter($external, fn ($checked) => $checked < $since));
	}

	/**
	 * Checks the URLs in parallel: a HEAD request first, servers that
	 * don't support it get a GET request that stops after the first bytes.
	 * URLs of private networks aren't requested (e.g. the router or cloud metadata),
	 * the request connects to the checked address, even if the DNS answer changes
	 *
	 * @return array<string, array{code: int, location: string|null, error: string|null, checked: int}>
	 */
	public static function request(array $urls): array
	{
		$results = [];

		if (!function_exists('curl_multi_init')) {
			return array_fill_keys($urls, ['code' => 0, 'location' => null, 'error' => 'curl', 'checked' => time()]);
		}

		$addresses = [];
		$resolve = [];

		foreach ($urls as $url) {
			$host = strtolower(trim((string)parse_url($url, PHP_URL_HOST), '[]'));
			$address = $addresses[$host] ??= static::address($host);

			if ($address === null) {
				$results[$url] = ['code' => 0, 'location' => null, 'error' => 'private', 'checked' => time()];
			} elseif ($address !== false) {
				$port = parse_url($url, PHP_URL_PORT) ?? (str_starts_with(strtolower($url), 'https:') ? 443 : 80);
				$resolve[$url] = ["{$host}:{$port}:" . (str_contains($address, ':') ? "[{$address}]" : $address)];
			}
		}

		$urls = array_values(array_diff($urls, array_keys($results)));

		foreach (['HEAD', 'GET'] as $method) {
			if ($urls === []) {
				break;
			}

			$multi = curl_multi_init();
			$handles = [];

			foreach ($urls as $url) {
				$handle = curl_init($url);
				curl_setopt_array($handle, [
					CURLOPT_NOBODY => $method === 'HEAD',
					CURLOPT_RETURNTRANSFER => false,
					CURLOPT_HEADER => false,
					CURLOPT_FOLLOWLOCATION => false,
					CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
					CURLOPT_RESOLVE => $resolve[$url] ?? [],
					CURLOPT_CONNECTTIMEOUT => 5,
					CURLOPT_TIMEOUT => (int)Seo::option('links.timeout'),
					CURLOPT_USERAGENT => Crawler::userAgent(),
					CURLOPT_HTTPHEADER => ['Accept: text/html,application/xhtml+xml,*/*;q=0.8'],
					// only the status is needed: stop at the first bytes of the body
					CURLOPT_WRITEFUNCTION => fn () => 0,
				]);
				curl_multi_add_handle($multi, $handle);
				$handles[$url] = $handle;
			}

			Crawler::perform($multi);

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
			$urls = $retry;
		}

		return $results;
	}

	/**
	 * Public IP address of the host, `null` if any of its addresses is private or reserved,
	 * `false` if it can't be resolved (the request reports why)
	 */
	protected static function address(string $host): string|false|null
	{
		$addresses = filter_var($host, FILTER_VALIDATE_IP) !== false
			? [$host]
			: (@gethostbynamel($host) ?: array_column(@dns_get_record($host, DNS_AAAA) ?: [], 'ipv6'));

		if ($addresses === []) {
			return false;
		}

		foreach ($addresses as $address) {
			if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
				return null;
			}
		}

		return $addresses[0];
	}
}
