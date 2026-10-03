<?php

namespace tobimori\Seo\Audit\Links;

use Kirby\Cache\Cache;
use Kirby\Cache\FileCache;
use Kirby\Cms\App;
use Kirby\Filesystem\Dir;
use Kirby\Toolkit\Str;

/**
 * Stored results of the link check:
 * - `pages`: rendered pages per language (`{language}/{page id}`), with their links & anchors
 * - `strings`: all linked URLs, pages refer to them by their index,
 *   as most links (navigation, footer) are the same on all pages
 * - `urls`: results of external URLs
 * - `targets`: comparable URLs of all pages to scan, e.g. to tell links to pages that aren't scanned yet from broken ones
 * - `invalidated`: time of the last "scan again", pages scanned before are scanned again
 * - `revision`: changes with every write, e.g. to cache the results derived from the index
 */
class Index
{
	protected const KEY = 'index';

	/**
	 * Handle of the lock file while this instance holds the lock
	 *
	 * @var resource|null
	 */
	protected $handle = null;

	public static function cache(): Cache
	{
		return App::instance()->cache('tobimori.seo.links');
	}

	public function read(): array
	{
		return [
			'pages' => [],
			'strings' => [],
			'urls' => [],
			'targets' => [],
			'invalidated' => 0,
			'revision' => null,
			...(static::cache()->get(self::KEY) ?? []),
		];
	}

	public function write(array $data): void
	{
		$data = static::compact($data);
		$data['revision'] = Str::random(8);

		static::cache()->set(self::KEY, $data);
	}

	/**
	 * Removes the URLs that no page links to anymore
	 */
	protected static function compact(array $data): array
	{
		$used = [];
		foreach ($data['pages'] as $entry) {
			foreach ($entry['links'] as $ids) {
				$used += array_flip($ids);
			}
		}

		if (count($used) === count($data['strings'])) {
			return $data;
		}

		ksort($used);
		$map = array_flip(array_keys($used));
		$data['strings'] = array_values(array_intersect_key($data['strings'], $used));

		foreach ($data['pages'] as $key => $entry) {
			foreach ($entry['links'] as $location => $ids) {
				$data['pages'][$key]['links'][$location] = array_map(fn ($id) => $map[$id], $ids);
			}
		}

		$data['urls'] = array_intersect_key($data['urls'], array_flip(array_map(fn ($url) => strtok($url, '#'), $data['strings'])));

		return $data;
	}

	/**
	 * Only one scan runs at a time, e.g. with multiple Panel users or a queue worker.
	 * Uses a file lock, which the system releases when the process ends, e.g. if a scan is interrupted
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
