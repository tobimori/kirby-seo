<?php

namespace tobimori\Seo\Audit\Links;

use Kirby\Cms\App;
use Kirby\Http\Router;
use Kirby\Uuid\Uuid;
use Throwable;
use tobimori\Seo\Seo;

/**
 * Results of the link check for one language: the state of each linked URL & the pages linking to it
 */
class Report
{
	/**
	 * States of links, by severity
	 */
	public const STATES = ['broken', 'anchor', 'redirect', 'unknown', 'ok'];

	public const SEVERITY = [
		'broken' => 'negative',
		'anchor' => 'notice',
		'redirect' => 'notice',
		'unknown' => 'unknown',
		'ok' => 'ok',
	];

	protected App $kirby;
	protected array $data;
	protected array|null $entries = null;
	protected array|null $targets = null;
	protected array|null $links = null;
	protected Router|null $router = null;

	public function __construct(protected string|null $language = null)
	{
		$this->kirby = App::instance();
		$this->language ??= $this->kirby->language()?->code();
		$this->data = (new Index())->read();
	}

	/**
	 * Hosts of the site: links to them are checked without requests
	 */
	public static function hosts(): array
	{
		$kirby = App::instance();
		$urls = [$kirby->url(), ...($kirby->multilang() ? $kirby->languages()->values(fn ($language) => $language->url()) : [])];
		$hosts = array_map(fn ($url) => parse_url($url, PHP_URL_HOST), $urls);

		return array_values(array_unique(array_map('strtolower', array_filter([...$hosts, ...(Seo::option('links.hosts') ?? [])]))));
	}

	public static function isInternal(string $url): bool
	{
		static $hosts = null;
		$hosts ??= static::hosts();

		return in_array(strtolower(parse_url($url, PHP_URL_HOST) ?? ''), $hosts, true);
	}

	/**
	 * Whether any page has been scanned yet
	 */
	public function isEmpty(): bool
	{
		return $this->data['pages'] === [];
	}

	/**
	 * All linked URLs (with fragments) of the language, with their state & the pages linking to them
	 *
	 * @return array<string, array{url: string, state: string, reason: string|null, target: string|null, code: int|null, internal: bool, pages: array<string, string>}>
	 */
	public function links(): array
	{
		if ($this->links !== null) {
			return $this->links;
		}

		$pages = [];

		foreach ($this->data['pages'] as $entry) {
			if ($entry['language'] !== $this->language) {
				continue;
			}

			foreach ($entry['links']['content'] as $id) {
				$pages[$id][$entry['page']] = 'content';
			}

			foreach ($entry['links']['layout'] as $id) {
				$pages[$id][$entry['page']] ??= 'layout';
			}
		}

		$this->links = [];

		foreach ($pages as $id => $linking) {
			$url = $this->data['strings'][$id];

			// e.g. links scanned before a scheme was ignored
			if (Crawler::isIgnored($url)) {
				continue;
			}
			$this->links[$url] = [
				'url' => $url,
				'internal' => static::isInternal($url),
				'pages' => $linking,
				'code' => null,
				'target' => null,
				'reason' => null,
				...$this->check($url),
			];
		}

		return $this->links;
	}

	/**
	 * Number of linked URLs per severity, cached until the index changes
	 *
	 * @return array{ok: int, notice: int, negative: int, unknown: int}
	 */
	public function stats(): array
	{
		$cache = Index::cache();
		$key = 'stats/' . ($this->language ?? 'default');
		$cached = $cache->get($key);

		if (($cached['revision'] ?? null) === $this->data['revision']) {
			return $cached['stats'];
		}

		$stats = ['ok' => 0, 'notice' => 0, 'negative' => 0, 'unknown' => 0];

		foreach ($this->links() as $link) {
			$stats[self::SEVERITY[$link['state']]]++;
		}

		$cache->set($key, ['revision' => $this->data['revision'], 'stats' => $stats]);

		return $stats;
	}

	/**
	 * State of a link: `ok`, `broken`, `redirect`, `anchor` (the page exists, the anchor doesn't)
	 * or `unknown` (can't be checked, or not checked yet)
	 */
	protected function check(string $url): array
	{
		// Kirby couldn't find the model, e.g. a deleted page linked in a writer field
		if (preg_match('#^(page|file)://#i', $url)) {
			return ['state' => 'broken', 'reason' => 'uuid'];
		}

		// links to other protocols that can't be checked, other schemes are
		// most likely errors, e.g. internal links of a previous CMS (`t3://page?uid=1`)
		if (!preg_match('#^https?://#i', $url)) {
			return preg_match('#^s?ftps?:#i', $url)
				? ['state' => 'unknown', 'reason' => 'scheme']
				: ['state' => 'broken', 'reason' => 'invalid'];
		}

		[$url, $fragment] = array_pad(explode('#', $url, 2), 2, null);

		return static::isInternal($url) ? $this->checkInternal($url, $fragment) : $this->checkExternal($url);
	}

	protected function checkInternal(string $url, string|null $fragment): array
	{
		if ($entry = $this->entries()[static::normalize($url)] ?? null) {
			return $this->checkPage($entry, $fragment);
		}

		// content representations, e.g. `/team/jane.vcf` for the template `team.vcf.php`
		if (
			preg_match('#\.([a-z0-9]+)$#i', parse_url($url, PHP_URL_PATH) ?? '', $match) &&
			($entry = $this->entries()[static::normalize(substr(strtok($url, '?'), 0, -strlen($match[0])))] ?? null)
		) {
			try {
				$this->kirby->page($entry['page'])?->representation($match[1]);
				return ['state' => 'ok'];
			} catch (Throwable) {
				return ['state' => 'broken', 'reason' => 'notFound', 'code' => 404];
			}
		}

		// a page that hasn't been scanned yet, e.g. during the first scan or right after publishing it
		if (isset($this->targets()[static::normalize($url)])) {
			return ['state' => 'unknown', 'reason' => 'unchecked'];
		}

		$path = $this->path($url);

		// permalinks redirect to the current URL of the model
		if (preg_match('#^@/(page|file)/([^/?]+)#', $path, $match)) {
			$model = Uuid::for("{$match[1]}://{$match[2]}")?->model();
			return $model
				? ['state' => 'redirect', 'reason' => 'permalink', 'target' => $model->url()]
				: ['state' => 'broken', 'reason' => 'uuid'];
		}

		$file = parse_url($url, PHP_URL_PATH) ?? '/';

		if ($this->isMedia($url) || $this->isStatic($file)) {
			return ['state' => 'ok'];
		}

		// routes of plugins & the config, e.g. the sitemap
		if ($this->hasRoute($path) || $this->hasRoute(trim(rawurldecode($file), '/'))) {
			return ['state' => 'unknown', 'reason' => 'route'];
		}

		// drafts aren't public
		if ($this->isDraft($path)) {
			return ['state' => 'broken', 'reason' => 'draft', 'code' => 404];
		}

		return ['state' => 'broken', 'reason' => 'notFound', 'code' => 404];
	}

	protected function checkPage(array $entry, string|null $fragment): array
	{
		$status = $entry['status'];

		return match (true) {
			$entry['error'] !== null => ['state' => 'unknown', 'reason' => 'render'],
			$status >= 300 && $status < 400 => ['state' => 'redirect', 'reason' => 'redirect', 'code' => $status, 'target' => $entry['location']],
			$status >= 400 => ['state' => 'broken', 'reason' => 'status', 'code' => $status],
			!$this->hasAnchor($entry, $fragment) => ['state' => 'anchor', 'reason' => 'anchor', 'target' => $fragment],
			default => ['state' => 'ok', 'code' => $status],
		};
	}

	protected function hasAnchor(array $entry, string|null $fragment): bool
	{
		if ($fragment === null || $fragment === '') {
			return true;
		}

		$fragment = rawurldecode($fragment);

		// browsers scroll to the top & support text fragments without matching elements
		return $fragment === 'top'
			|| str_starts_with($fragment, ':~:')
			|| in_array($fragment, $entry['ids'], true);
	}

	protected function checkExternal(string $url): array
	{
		$result = $this->data['urls'][$url] ?? null;
		$code = $result['code'] ?? 0;

		return match (true) {
			$result === null, $result['error'] === 'curl' => ['state' => 'unknown', 'reason' => 'unchecked'],
			$result['error'] === 'timeout' => ['state' => 'unknown', 'reason' => 'timeout'],
			// not requested, e.g. a server in the network of the site
			$result['error'] === 'private' => ['state' => 'unknown', 'reason' => 'private'],
			$result['error'] !== null => ['state' => 'broken', 'reason' => 'unreachable'],
			$code >= 200 && $code < 300 => ['state' => 'ok', 'code' => $code],
			$code >= 300 && $code < 400 => ['state' => 'redirect', 'reason' => 'redirect', 'code' => $code, 'target' => $result['location']],
			// gone, or the server has an error
			in_array($code, [404, 410], true) || $code >= 500 => ['state' => 'broken', 'reason' => 'status', 'code' => $code],
			// e.g. 403 or 429: the server doesn't answer requests of bots
			default => ['state' => 'unknown', 'reason' => 'blocked', 'code' => $code],
		};
	}

	/**
	 * Scanned pages of all languages by their URL, as pages might link to other languages
	 */
	protected function entries(): array
	{
		if ($this->entries === null) {
			$this->entries = [];

			foreach ($this->data['pages'] as $entry) {
				$this->entries[static::normalize($entry['url'])] = $entry;
			}
		}

		return $this->entries;
	}

	/**
	 * Comparable URLs of all pages the scan renders (as keys)
	 */
	protected function targets(): array
	{
		return $this->targets ??= array_flip($this->data['targets']);
	}

	/**
	 * Comparable form of page URLs: without query, fragment, Kirby's params & trailing slashes
	 */
	public static function normalize(string $url): string
	{
		$parts = parse_url($url);
		$path = rawurldecode($parts['path'] ?? '');
		// params, e.g. `/blog/tag:kirby`
		$path = preg_replace('#(/[^/]+:[^/]*)+/?$#', '', $path);

		return strtolower($parts['host'] ?? '') . (isset($parts['port']) ? ":{$parts['port']}" : '') . '/' . trim($path, '/');
	}

	/**
	 * Path of an internal URL without the language prefix
	 */
	protected function path(string $url): string
	{
		$path = trim(rawurldecode(parse_url($url, PHP_URL_PATH) ?? ''), '/');

		if ($this->kirby->multilang()) {
			foreach ($this->kirby->languages() as $language) {
				$prefix = trim(parse_url($language->url(), PHP_URL_PATH) ?? '', '/');

				if ($prefix !== '' && ($path === $prefix || str_starts_with($path, "{$prefix}/"))) {
					return ltrim(substr($path, strlen($prefix)), '/');
				}
			}
		}

		return $path;
	}

	/**
	 * Whether the path leads to a draft (or a page below one), by the slugs of any language
	 */
	protected function isDraft(string $path): bool
	{
		$languages = $this->kirby->multilang() ? $this->kirby->languages()->codes() : [null];
		$model = $this->kirby->site();

		foreach (explode('/', $path) as $segment) {
			$model = $model->childrenAndDrafts()->filter(
				fn ($page) => in_array($segment, array_map(fn ($language) => $page->slug($language), $languages), true)
			)->first();

			if ($model === null) {
				return false;
			}

			if ($model->isDraft()) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Files & thumbs in the media folder: they are created on the first request,
	 * so the file needs to exist in the content (thumbs start with the file's name)
	 */
	protected function isMedia(string $url): bool
	{
		$media = rtrim($this->kirby->url('media'), '/') . '/';

		if (!str_starts_with($url, $media)) {
			return false;
		}

		$segments = explode('/', rawurldecode(strtok(substr($url, strlen($media)), '?')));
		$filename = array_pop($segments);
		// the hash of the file version
		array_pop($segments);

		$parent = match (array_shift($segments)) {
			'pages' => $this->kirby->page(implode('/', $segments)),
			'site' => $this->kirby->site(),
			default => null,
		};

		if ($parent === null) {
			return false;
		}

		foreach ($parent->files() as $file) {
			if ($file->filename() === $filename || str_starts_with($filename, $file->name() . '-')) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Files in the public folder, e.g. assets
	 */
	protected function isStatic(string $path): bool
	{
		$root = realpath($this->kirby->root('index'));
		$file = realpath($root . '/' . rawurldecode(ltrim($path, '/')));

		return $root !== false && $file !== false && is_file($file) && str_starts_with($file, $root . DIRECTORY_SEPARATOR);
	}

	protected function hasRoute(string $path): bool
	{
		if ($this->router === null) {
			$config = $this->kirby->option('routes', []);
			$routes = [
				...$this->kirby->extensions('routes'),
				...(is_callable($config) ? $config($this->kirby) : $config),
			];

			$this->router = new Router(array_filter($routes, fn ($route) => isset($route['pattern'], $route['action'])));
		}

		try {
			$this->router->find($path, 'GET');
			return true;
		} catch (Throwable) {
			return false;
		}
	}
}
