<?php

namespace tobimori\Seo\Links;

use Kirby\Cache\Cache;
use Kirby\Cms\App;
use Kirby\Toolkit\Str;

/**
 * Stored results of the link check:
 * - `pages`: rendered pages per language (`{language}/{page id}`), with their links & anchors
 * - `strings`: all linked URLs, pages refer to them by their index,
 *   as most links (navigation, footer) are the same on all pages
 * - `urls`: results of external URLs
 * - `invalidated`: time of the last "scan again", pages scanned before are scanned again
 * - `revision`: changes with every write, e.g. to cache the results derived from the index
 */
class Index
{
	protected const KEY = 'index';
	protected const LOCK = 'lock';

	protected string|null $token = null;

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
	 * Locks expire, in case a scan is interrupted
	 */
	public function lock(int $seconds): bool
	{
		$lock = static::cache()->get(self::LOCK);

		if ($lock !== null && $lock !== $this->token) {
			return false;
		}

		$this->token ??= Str::random(16);
		// the cache expires in minutes
		static::cache()->set(self::LOCK, $this->token, (int)ceil(($seconds + 60) / 60));

		return static::cache()->get(self::LOCK) === $this->token;
	}

	/**
	 * Waits for a running scan step to finish, e.g. to change the index from the Panel
	 */
	public function wait(int $seconds, int $timeout = 15): bool
	{
		$until = microtime(true) + $timeout;

		while (!$this->lock($seconds)) {
			if (microtime(true) >= $until) {
				return false;
			}

			usleep(250_000);
		}

		return true;
	}

	public function unlock(): void
	{
		if ($this->token !== null && static::cache()->get(self::LOCK) === $this->token) {
			static::cache()->remove(self::LOCK);
		}

		$this->token = null;
	}

	public function isLocked(): bool
	{
		return static::cache()->get(self::LOCK) !== null;
	}
}
