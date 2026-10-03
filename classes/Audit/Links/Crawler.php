<?php

namespace tobimori\Seo\Audit\Links;

use Closure;
use CurlMultiHandle;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Kirby\Cms\App;
use Kirby\Cms\Page;
use Kirby\Cms\Responder;
use Kirby\Http\Request;
use Throwable;
use tobimori\Seo\Seo;

/**
 * Extracts links and anchor IDs from rendered or fetched pages
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
	 * @param \Closure(string $key, int $code, string|null $location): void $onExit Called if a template ends
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
	 * Defaults to HTTP in CLI mode with an absolute site URL, so template exits do not end workers.
	 * An explicit option overrides the default; HTTP always requires cURL
	 */
	public static function usesHttp(): bool
	{
		$option = Seo::option('links.http');

		if ($option === false || !function_exists('curl_multi_init')) {
			return false;
		}

		return $option === true || (PHP_SAPI === 'cli' && preg_match('#^https?://#i', App::instance()->url()) === 1);
	}

	/**
	 * @return array{status: int, location: string|null, error: string|null, links: array<string>, ids: array<string>}
	 */
	public function render(string $key, Page $page, string|null $language): array
	{
		$kirby = App::instance();
		$url = $page->url($language);
		$restore = static::isolate($kirby, $url);
		$level = ob_get_level();

		static::$current = ['key' => $key, 'onExit' => $this->onExit];
		ob_start();

		try {
			// as a visitor sees it: templates might show different content to logged-in users
			$html = $kirby->impersonate('nobody', function () use ($kirby, $page, $language) {
				$kirby->site()->visit($page, $language);
				return $page->render(versionId: 'latest');
			});

			// the router answers with 404 for the error page
			$code = $kirby->response()->code() ?? ($page->isErrorPage() ? 404 : 200);

			return static::result($code, $kirby->response()->header('Location'), $html, $url);
		} catch (Throwable $e) {
			return [...static::result(500, null, '', $url), 'error' => $e->getMessage()];
		} finally {
			// templates might leave output buffers open
			while (ob_get_level() > $level) {
				ob_end_clean();
			}

			static::$current = null;
			$restore();
		}
	}

	public static function timeout(): int
	{
		return max(30, (int)Seo::option('links.timeout'));
	}

	/**
	 * @param array<string, string> $urls URLs of the pages by their key
	 * @return array<string, array{status: int, location: string|null, error: string|null, links: array<string>, ids: array<string>}>
	 */
	public static function fetch(array $urls): array
	{
		$multi = curl_multi_init();
		$handles = [];

		foreach ($urls as $key => $url) {
			$handle = curl_init($url);
			curl_setopt_array($handle, [
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_FOLLOWLOCATION => false,
				CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
				CURLOPT_CONNECTTIMEOUT => 5,
				CURLOPT_TIMEOUT => static::timeout(),
				CURLOPT_ENCODING => '',
				CURLOPT_USERAGENT => static::userAgent(),
				CURLOPT_HTTPHEADER => ['Accept: text/html,application/xhtml+xml,*/*;q=0.8'],
			]);
			curl_multi_add_handle($multi, $handle);
			$handles[$key] = $handle;
		}

		static::perform($multi);

		$results = [];

		foreach ($handles as $key => $handle) {
			$code = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);

			$results[$key] = $code === 0
				? [...static::result(500, null, '', $urls[$key]), 'error' => curl_error($handle) ?: 'connection']
				: static::result(
					status: $code,
					location: curl_getinfo($handle, CURLINFO_REDIRECT_URL) ?: null,
					html: (string)curl_multi_getcontent($handle),
					url: $urls[$key],
					isHtml: str_contains((string)curl_getinfo($handle, CURLINFO_CONTENT_TYPE), 'html'),
				);

			curl_multi_remove_handle($multi, $handle);
		}

		curl_multi_close($multi);

		return $results;
	}

	/**
	 * Uses a browser-compatible user agent because some servers reject unknown clients
	 */
	public static function userAgent(): string
	{
		return 'Mozilla/5.0 (compatible; Kirby SEO link checker; +' . App::instance()->url() . ')';
	}

	public static function perform(CurlMultiHandle $multi): void
	{
		do {
			$status = curl_multi_exec($multi, $running);

			// `-1` if there's nothing to wait for yet, without a pause this would be a busy loop
			if ($running && curl_multi_select($multi) === -1) {
				usleep(10_000);
			}
		} while ($running && $status === CURLM_OK);

		// `curl_errno()` of the handles is only set once their messages are read
		do {
			$message = curl_multi_info_read($multi);
		} while ($message !== false);
	}

	/**
	 * Links & anchors of successful HTML responses, redirects and errors have none
	 */
	protected static function result(int $status, string|null $location, string $html, string $url, bool $isHtml = true): array
	{
		return [
			'status' => $status,
			'location' => $location,
			'error' => null,
			...($status >= 200 && $status < 300 && $isHtml ? static::extract($html, $url) : ['links' => [], 'ids' => []]),
		];
	}

	/**
	 * Prevents templates from treating the Panel's POST body or cookies as visitor input.
	 * Returns a closure that restores the request and CMS state after rendering
	 */
	protected static function isolate(App $kirby, string $url): Closure
	{
		$site = $kirby->site();
		$globals = [$_GET, $_POST, $_REQUEST, $_FILES, $_COOKIE];
		$language = $kirby->language()?->code();
		$translation = $kirby->translation()->code();
		$data = $kirby->data;
		$page = (fn () => $this->page ?? null)->call($site);
		$state = (fn () => [$this->request, $this->response, $this->path])->call($kirby);

		$_GET = $_POST = $_REQUEST = $_FILES = $_COOKIE = [];

		(function () use ($url) {
			$this->request = new class (['method' => 'GET', 'url' => $url, 'query' => [], 'body' => [], 'files' => []]) extends Request {
				// none of the headers of the current request, e.g. its cookies or referer
				public function headers(): array
				{
					return [];
				}
			};
			// templates might change the status code or headers
			$this->response = new Responder();
			$this->path = null;
		})->call($kirby);

		return function () use ($kirby, $site, $globals, $language, $translation, $data, $page, $state) {
			[$_GET, $_POST, $_REQUEST, $_FILES, $_COOKIE] = $globals;
			(function () use ($state) {
				[$this->request, $this->response, $this->path] = $state;
			})->call($kirby);
			(function () use ($page) {
				$this->page = $page;
			})->call($site);
			$kirby->data = $data;
			$kirby->setCurrentLanguage($language);
			$kirby->setCurrentTranslation($translation);
		};
	}

	/**
	 * @return array{links: array<string>, ids: array<string>}
	 */
	public static function extract(string $html, string $url): array
	{
		$document = new DOMDocument();
		// without the encoding, libxml reads the HTML as ISO-8859-1
		@$document->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
		$xpath = new DOMXPath($document);

		$base = $xpath->query('//base[@href]')->item(0);
		$base = $base instanceof DOMElement ? static::resolve($base->getAttribute('href'), $url) ?? $url : $url;
		$links = [];

		foreach ($xpath->query('//a[@href] | //area[@href]') as $element) {
			if (!($href = static::resolve($element->getAttribute('href'), $base))) {
				continue;
			}

			$links[$href] = true;
		}

		$ids = [];
		foreach ($xpath->query('//*[@id] | //a[@name]') as $element) {
			$ids[$element->getAttribute('id') ?: $element->getAttribute('name')] = true;
		}

		return [
			'links' => array_keys($links),
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
	 * Saves progress if a template exits before rendering completes.
	 * Replaces the template's response with a retry signal for the Panel
	 */
	protected static function shutdown(): void
	{
		if (static::$current === null) {
			return;
		}

		// the CLI doesn't keep a list of headers, but the status code
		$code = http_response_code();
		$location = null;

		foreach (headers_list() as $header) {
			if (stripos($header, 'location:') === 0) {
				$location = trim(substr($header, 9));
			}
		}

		(static::$current['onExit'])(static::$current['key'], is_int($code) ? $code : 200, $location);

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
