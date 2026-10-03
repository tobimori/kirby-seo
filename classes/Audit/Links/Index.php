<?php

namespace tobimori\Seo\Audit\Links;

use Kirby\Cache\Cache;
use Kirby\Cache\FileCache;
use Kirby\Cms\App;
use Kirby\Filesystem\Dir;
use Kirby\Toolkit\Str;

/**
 * Stores link audit results and provides locking for scan updates
 */
class Index
{
	protected const KEY = 'index';
	protected const REVISION = 'revision';
	protected const STATE = 'state';

	/**
	 * @var resource|null
	 */
	protected $handle = null;

	public static function cache(): Cache
	{
		return App::instance()->cache('tobimori.seo.links');
	}

	/**
	 * Returns stored scan data with defaults for an empty index.
	 *
	 * - `pages`: scan results by `{language}/{page id}`, including links and anchor IDs
	 * - `strings`: shared URL table; page links refer to its integer keys to avoid repeated strings
	 * - `urls`: external URL check results
	 * - `targets`: normalized page URLs, used to distinguish unscanned targets from missing pages
	 * - `invalidated`: timestamp used to invalidate page fingerprints and external checks
	 * - `revision`: token changed on each write to invalidate derived reports
	 */
	public function read(): array
	{
		$data = [
			'pages' => [],
			'strings' => [],
			'urls' => [],
			'targets' => [],
			'invalidated' => 0,
			'revision' => null,
			...(static::cache()->get(self::KEY) ?? []),
		];

		foreach ($data['pages'] as $key => $entry) {
			// Older scans stored links in separate content and layout lists
			if (isset($entry['links']['content'])) {
				$data['pages'][$key]['links'] = array_values(array_unique([...$entry['links']['content'], ...$entry['links']['layout']]));
			}
		}

		return $data;
	}

	/**
	 * Stores compacted scan data and updates the revision and progress summary.
	 * URL IDs in the stored data can differ from those in the supplied array
	 */
	public function write(array $data): void
	{
		$data = static::compact($data);
		$data['revision'] = Str::random(8);

		static::cache()->set(self::KEY, $data);
		// stored separately, so results derived from the index can be validated without reading it
		static::cache()->set(self::REVISION, $data['revision']);
		static::cache()->set(self::STATE, static::summarize($data));
	}

	/**
	 * Reads the progress summary without loading page links and anchors.
	 * Falls back to the full index if no summary is stored
	 */
	public function state(): array
	{
		return static::cache()->get(self::STATE) ?? static::summarize($this->read());
	}

	/**
	 * Extracts page fingerprints, normalized URLs, and external check times for progress reporting
	 *
	 * @return array{invalidated: int, pages: array<string, array{0: string|null, 1: string}>, external: array<string, int>}
	 */
	public static function summarize(array $data): array
	{
		return [
			'invalidated' => $data['invalidated'],
			'pages' => array_map(fn ($entry) => [$entry['fingerprint'], Report::normalize($entry['url'])], $data['pages']),
			'external' => static::external($data),
		];
	}

	/**
	 * All external URLs linked from any page (without fragments) & when they have been checked (`0` if never)
	 *
	 * @return array<string, int>
	 */
	public static function external(array $data): array
	{
		$urls = [];

		foreach ($data['strings'] as $url) {
			if (preg_match('#^https?://#i', $url) && !Report::isInternal($url)) {
				$url = strtok($url, '#');
				$urls[$url] = $data['urls'][$url]['checked'] ?? 0;
			}
		}

		return $urls;
	}

	/**
	 * Reads the revision token without loading the index, unless no separate token is stored
	 */
	public static function revision(): string|null
	{
		return static::cache()->get(self::REVISION) ?? (new static())->read()['revision'];
	}

	/**
	 * Removes unreferenced URLs and remaps page links to the remaining string IDs
	 */
	protected static function compact(array $data): array
	{
		$used = [];
		foreach ($data['pages'] as $entry) {
			$used += array_flip($entry['links']);
		}

		if (count($used) === count($data['strings'])) {
			return $data;
		}

		ksort($used);
		$map = array_flip(array_keys($used));
		$data['strings'] = array_values(array_intersect_key($data['strings'], $used));

		foreach ($data['pages'] as $key => $entry) {
			$data['pages'][$key]['links'] = array_map(fn ($id) => $map[$id], $entry['links']);
		}

		$data['urls'] = array_intersect_key($data['urls'], array_flip(array_map(fn ($url) => strtok($url, '#'), $data['strings'])));

		return $data;
	}

	/**
	 * Acquires a non-blocking exclusive file lock.
	 * The operating system releases the lock when the process exits
	 */
	public function lock(): bool
	{
		if ($this->handle !== null) {
			return true;
		}

		$handle = @fopen(static::lockFile(), 'c');

		if ($handle === false) {
			return false;
		}

		if (!flock($handle, LOCK_EX | LOCK_NB)) {
			fclose($handle);
			return false;
		}

		$this->handle = $handle;

		return true;
	}

	/**
	 * Waits for a running scan step to finish, e.g. to change the index from the Panel
	 */
	public function wait(int $timeout = 15): bool
	{
		$until = microtime(true) + $timeout;

		while (!$this->lock()) {
			if (microtime(true) >= $until) {
				return false;
			}

			usleep(250_000);
		}

		return true;
	}

	public function unlock(): void
	{
		if ($this->handle !== null) {
			flock($this->handle, LOCK_UN);
			fclose($this->handle);
			$this->handle = null;
		}
	}

	public function isLocked(): bool
	{
		if ($this->handle !== null) {
			return true;
		}

		if (!$this->lock()) {
			return true;
		}

		$this->unlock();

		return false;
	}

	/**
	 * Next to the index if it's stored in files, so all processes using the same index share the lock
	 */
	protected static function lockFile(): string
	{
		$cache = static::cache();
		$root = $cache instanceof FileCache ? $cache->root() : App::instance()->root('cache');
		Dir::make($root);

		return $root . '/' . ($cache instanceof FileCache ? 'links' : 'tobimori-seo-links-' . md5(App::instance()->url())) . '.lock';
	}
}
