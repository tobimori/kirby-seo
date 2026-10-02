<?php

namespace tobimori\Seo\Links;

use Closure;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Kirby\Cms\App;
use Kirby\Cms\Page;
use Throwable;

/**
 * Renders pages like visitors see them & extracts their links and anchors
 */
class Crawler
{
	/**
	 * Schemes that aren't checked: no page to load, or opened by other apps
	 */
	public const IGNORED_SCHEMES = [
		'mailto', 'tel', 'fax', 'sms', 'callto', 'sip', 'javascript', 'data', 'blob',
		'geo', 'maps', 'webcal', 'skype', 'whatsapp', 'tg', 'signal', 'facetime', 'msteams', 'zoommtg',
	];

	protected static bool $guarded = false;
	protected static array|null $current = null;

	/**
	 * @param \Closure(string $key, string|null $location): void $onExit Called if a template ends
	 *   the script while rendering (e.g. `go()` for a redirect), to store what happened
	 */
	public function __construct(protected Closure $onExit)
	{
		if (!static::$guarded) {
			register_shutdown_function(static::shutdown(...));
			static::$guarded = true;
		}
	}

	/**
	 * Renders the page in the given language
	 *
	 * @return array{status: int, location: string|null, error: string|null, links: array{content: array<string>, layout: array<string>}, ids: array<string>}
	 */
	public function crawl(string $key, Page $page, string|null $language): array
	{
		$kirby = App::instance();
		$response = $kirby->response();
		$state = $response->toArray();
		$languageBefore = $kirby->language()?->code();
		$translationBefore = $kirby->translation()->code();

		// a fresh response per page, templates might change the status code or headers
		$response->code(200);
		$response->headers([]);

		static::$current = ['key' => $key, 'onExit' => $this->onExit];
		ob_start();

		try {
			// as a visitor sees it: templates might show different content to logged-in users
			$html = $kirby->impersonate('nobody', function () use ($kirby, $page, $language) {
				$kirby->site()->visit($page, $language);
				return $page->render(versionId: 'latest');
			});

			$status = $response->code() ?? 200;
			$location = $response->header('Location');

			return [
				'status' => $status,
				'location' => $location,
				'error' => null,
				...($status >= 300 ? ['links' => ['content' => [], 'layout' => []], 'ids' => []] : static::extract($html, $page->url($language))),
			];
		} catch (Throwable $e) {
			return [
				'status' => 500,
				'location' => null,
				'error' => $e->getMessage(),
				'links' => ['content' => [], 'layout' => []],
				'ids' => [],
			];
		} finally {
			ob_end_clean();
			static::$current = null;

			$response->fromArray($state);
			$kirby->setCurrentLanguage($languageBefore);
			$kirby->setCurrentTranslation($translationBefore);
		}
	}

	/**
	 * Links of the HTML, split by whether they are part of the main content
	 * or of the layout (navigation, footer, …), and the ids anchors can point to.
	 * Pages without a `<main>` element only have content links.
	 *
	 * @return array{links: array{content: array<string>, layout: array<string>}, ids: array<string>}
	 */
	public static function extract(string $html, string $url): array
	{
		$document = new DOMDocument();
		// without the encoding, libxml reads the HTML as ISO-8859-1
		@$document->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
		$xpath = new DOMXPath($document);

		$base = $xpath->query('//base[@href]')->item(0);
		$base = $base instanceof DOMElement ? static::resolve($base->getAttribute('href'), $url) ?? $url : $url;
		$hasMain = $xpath->query('//main')->length > 0;
		$links = ['content' => [], 'layout' => []];

		foreach ($xpath->query('//a[@href] | //area[@href]') as $element) {
			if (!($href = static::resolve($element->getAttribute('href'), $base))) {
				continue;
			}

			$location = !$hasMain || $xpath->query('ancestor::main', $element)->length > 0 ? 'content' : 'layout';
			$links[$location][$href] = true;
		}

		$ids = [];
		foreach ($xpath->query('//*[@id] | //a[@name]') as $element) {
			$ids[$element->getAttribute('id') ?: $element->getAttribute('name')] = true;
		}

		return [
			'links' => [
				'content' => array_keys($links['content']),
				// links in both places are content links
				'layout' => array_keys(array_diff_key($links['layout'], $links['content'])),
			],
			'ids' => array_keys($ids),
		];
	}

	/**
	 * Absolute URL of a link, `null` for links that aren't checked
	 */
	public static function resolve(string $href, string $base): string|null
	{
		$href = trim($href);

		if ($href === '' || $href === '#') {
			return null;
		}

		if (preg_match('/^([a-z][a-z0-9+.-]*):/i', $href)) {
			return static::isIgnored($href) ? null : $href;
		}

		$parts = parse_url($base);
		$origin = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '') . (isset($parts['port']) ? ":{$parts['port']}" : '');
		$path = $parts['path'] ?? '/';
		$query = isset($parts['query']) ? "?{$parts['query']}" : '';

		return match (true) {
			str_starts_with($href, '//') => ($parts['scheme'] ?? 'https') . ':' . $href,
			str_starts_with($href, '#') => $origin . $path . $query . $href,
			str_starts_with($href, '?') => $origin . $path . $href,
			str_starts_with($href, '/') => $origin . static::normalizePath($href),
			default => $origin . static::normalizePath(preg_replace('#[^/]*$#', '', $path) . $href),
		};
	}

	public static function isIgnored(string $url): bool
	{
		return preg_match('/^([a-z][a-z0-9+.-]*):/i', $url, $match) === 1
			&& in_array(strtolower($match[1]), self::IGNORED_SCHEMES, true);
	}

	/**
	 * Removes `.` & `..` segments
	 */
	protected static function normalizePath(string $path): string
	{
		$suffix = '';
		if (preg_match('/[?#].*$/', $path, $match)) {
			$suffix = $match[0];
			$path = substr($path, 0, -strlen($suffix));
		}

		$segments = [];
		foreach (explode('/', $path) as $segment) {
			match ($segment) {
				'.' => null,
				'..' => array_pop($segments),
				default => $segments[] = $segment,
			};
		}

		return '/' . ltrim(implode('/', $segments), '/') . $suffix;
	}

	/**
	 * Templates might end the script while rendering, e.g. with `go()` for a redirect.
	 * The page is stored as redirect, so the next scan continues with the next page,
	 * and the redirect response is replaced, as it belongs to the page, not the request
	 */
	protected static function shutdown(): void
	{
		if (static::$current === null) {
			return;
		}

		$location = null;
		foreach (headers_list() as $header) {
			if (stripos($header, 'location:') === 0) {
				$location = trim(substr($header, 9));
			}
		}

		(static::$current['onExit'])(static::$current['key'], $location);

		if (PHP_SAPI !== 'cli' && !headers_sent()) {
			while (ob_get_level() > 0) {
				ob_end_clean();
			}

			header_remove();
			http_response_code(200);
			header('Content-Type: application/json; charset=UTF-8');
			echo json_encode(['retry' => true]);
		}
	}
}
