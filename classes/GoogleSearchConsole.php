<?php

namespace tobimori\Seo;

use Kirby\Cache\Cache;
use Kirby\Cms\App;
use Kirby\Cms\Page;
use Kirby\Data\Json;
use Kirby\Filesystem\F;
use Kirby\Http\Remote;
use Kirby\Http\Uri;

use function array_slice;

class GoogleSearchConsole
{
	protected const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
	protected const TOKEN_URL = 'https://oauth2.googleapis.com/token';
	protected const SCOPES = 'email https://www.googleapis.com/auth/webmasters.readonly';
	protected const CACHE_DURATION = 60 * 24; // 24 hours in minutes

	protected static ?array $tokens = null;

	protected static function cache(): Cache
	{
		return App::instance()->cache('tobimori.seo.searchConsole');
	}

	/**
	 * Get OAuth credentials from config
	 */
	public static function credentials(): ?array
	{
		return Seo::option('searchConsole.credentials.web');
	}

	/**
	 * Check if credentials are configured
	 */
	public static function hasCredentials(): bool
	{
		$credentials = static::credentials();
		return !empty($credentials['client_id']) && !empty($credentials['client_secret']);
	}

	/**
	 * Get the token file path
	 */
	protected static function tokenPath(): string
	{
		$path = Seo::option('searchConsole.tokenPath');
		return is_callable($path) ? $path() : $path;
	}

	/**
	 * Load tokens from file
	 */
	public static function tokens(): ?array
	{
		if (static::$tokens !== null) {
			return static::$tokens;
		}

		$path = static::tokenPath();
		if (!F::exists($path)) {
			return null;
		}

		static::$tokens = Json::read($path);
		return static::$tokens;
	}

	/**
	 * Save tokens to file
	 */
	protected static function saveTokens(array $tokens): void
	{
		static::$tokens = $tokens;
		Json::write(static::tokenPath(), $tokens);
	}

	/**
	 * Check if we have valid tokens
	 */
	public static function isConnected(): bool
	{
		$tokens = static::tokens();
		return !empty($tokens['access_token']) && !empty($tokens['refresh_token']);
	}

	/**
	 * Get the authorization URL
	 */
	public static function authUrl(string $redirectUri, string $state): string
	{
		$credentials = static::credentials();

		$uri = new Uri(static::AUTH_URL);
		$uri->query()->merge([
			'client_id' => $credentials['client_id'],
			'redirect_uri' => $redirectUri,
			'response_type' => 'code',
			'access_type' => 'offline',
			'prompt' => 'consent',
			'scope' => static::SCOPES,
			'state' => $state
		]);

		return $uri->toString();
	}

	/**
	 * Exchange authorization code for tokens
	 */
	public static function exchangeCode(string $code, string $redirectUri): array
	{
		$credentials = static::credentials();

		$response = Remote::request(static::TOKEN_URL, [
			'method' => 'POST',
			'headers' => [
				'Content-Type' => 'application/x-www-form-urlencoded'
			],
			'data' => [
				'client_id' => $credentials['client_id'],
				'client_secret' => $credentials['client_secret'],
				'code' => $code,
				'grant_type' => 'authorization_code',
				'redirect_uri' => $redirectUri
			]
		]);

		$data = $response->json();

		if (isset($data['error'])) {
			throw new \Exception($data['error_description'] ?? $data['error']);
		}

		// store tokens with expiry timestamp
		$tokens = [
			'access_token' => $data['access_token'],
			'refresh_token' => $data['refresh_token'],
			'expires_at' => time() + $data['expires_in']
		];

		static::saveTokens($tokens);
		return $tokens;
	}

	/**
	 * Refresh the access token
	 */
	public static function refreshToken(): string
	{
		$credentials = static::credentials();
		$tokens = static::tokens();

		if (empty($tokens['refresh_token'])) {
			throw new \Exception('No refresh token available');
		}

		$response = Remote::request(static::TOKEN_URL, [
			'method' => 'POST',
			'headers' => [
				'Content-Type' => 'application/x-www-form-urlencoded'
			],
			'data' => [
				'client_id' => $credentials['client_id'],
				'client_secret' => $credentials['client_secret'],
				'refresh_token' => $tokens['refresh_token'],
				'grant_type' => 'refresh_token'
			]
		]);

		$data = $response->json();

		if (isset($data['error'])) {
			throw new \Exception($data['error_description'] ?? $data['error']);
		}

		$tokens['access_token'] = $data['access_token'];
		$tokens['expires_at'] = time() + $data['expires_in'];

		static::saveTokens($tokens);
		return $tokens['access_token'];
	}

	/**
	 * Get a valid access token, refreshing if needed
	 */
	public static function accessToken(): string
	{
		$tokens = static::tokens();

		if (empty($tokens['access_token'])) {
			throw new \Exception('Not connected to Google Search Console');
		}

		// refresh if expired or expiring soon (within 5 min)
		if ($tokens['expires_at'] < time() + 300) {
			return static::refreshToken();
		}

		return $tokens['access_token'];
	}

	/**
	 * Get the connected property URL
	 */
	public static function property(): ?string
	{
		$tokens = static::tokens();
		return $tokens['property'] ?? null;
	}

	/**
	 * Find the best matching property for a site URL
	 */
	public static function findMatchingProperty(string $siteUrl): ?string
	{
		$properties = static::listProperties();
		if (empty($properties)) {
			return null;
		}

		$siteHost = parse_url($siteUrl, PHP_URL_HOST);

		foreach ($properties as $p) {
			$propUrl = $p['siteUrl'];

			// check domain properties (sc-domain:example.com)
			if (str_starts_with($propUrl, 'sc-domain:')) {
				$domain = substr($propUrl, 10);
				if ($domain === $siteHost || str_ends_with($siteHost, ".{$domain}")) {
					return $propUrl;
				}
			}

			// check URL prefix properties
			if (str_starts_with($siteUrl, $propUrl) || $propUrl === "{$siteUrl}/") {
				return $propUrl;
			}
		}

		// fallback to first property
		return $properties[0]['siteUrl'] ?? null;
	}

	/**
	 * Set the connected property URL
	 */
	public static function setProperty(string $property): void
	{
		$tokens = static::tokens() ?? [];
		$tokens['property'] = $property;
		static::saveTokens($tokens);
	}

	/**
	 * Disconnect (remove tokens)
	 */
	public static function disconnect(): void
	{
		$path = static::tokenPath();
		if (F::exists($path)) {
			F::remove($path);
		}
		static::$tokens = null;
	}

	/**
	 * List available GSC properties
	 */
	public static function listProperties(): array
	{
		$response = Remote::request('https://www.googleapis.com/webmasters/v3/sites', [
			'method' => 'GET',
			'headers' => [
				'Authorization' => 'Bearer ' . static::accessToken()
			]
		]);

		$data = $response->json();

		if (isset($data['error'])) {
			throw new \Exception($data['error']['message'] ?? 'Failed to list properties');
		}

		return $data['siteEntry'] ?? [];
	}

	/**
	 * Query search analytics data (fetches max 25k rows from Google, cached for 24h)
	 */
	public static function query(array $options = []): array
	{
		$property = static::property();
		if (!$property) {
			throw new \Exception('No property selected');
		}

		$body = [
			'startDate' => $options['startDate'] ?? date('Y-m-d', strtotime('-28 days')),
			'endDate' => $options['endDate'] ?? date('Y-m-d', strtotime('-1 day')),
			'dimensions' => $options['dimensions'] ?? ['query'],
			'rowLimit' => 25000
		];

		if (!empty($options['page'])) {
			$body['dimensionFilterGroups'] = [[
				'filters' => [[
					'dimension' => 'page',
					'operator' => $options['pageOperator'] ?? 'equals',
					'expression' => $options['page']
				]]
			]];
		}

		$cacheKey = md5($property . json_encode($body));

		$cached = static::cache()->get($cacheKey);
		if ($cached !== null) {
			return $cached;
		}

		$uri = new Uri('https://www.googleapis.com/webmasters/v3/sites');
		$uri->setPath($uri->path() . '/' . urlencode($property) . '/searchAnalytics/query');

		$response = Remote::request($uri->toString(), [
			'method' => 'POST',
			'headers' => [
				'Authorization' => 'Bearer ' . static::accessToken(),
				'Content-Type' => 'application/json'
			],
			'data' => json_encode($body)
		]);

		$data = $response->json();

		if (isset($data['error'])) {
			throw new \Exception($data['error']['message'] ?? 'Failed to query search analytics');
		}

		$rows = $data['rows'] ?? [];

		static::cache()->set($cacheKey, $rows, static::CACHE_DURATION);

		return $rows;
	}

	/**
	 * Query search data for a Kirby model (page or site), sorted by metric
	 */
	public static function queryForModel($model, string $metric = 'clicks', int $limit = 10, bool $asc = false): array
	{
		if ($model instanceof Page) {
			// try exact URL match first
			$data = static::query(['page' => $model->url()]);

			// fallback: match by path
			if (empty($data)) {
				$path = $model->uri();
				if ($path) {
					$data = static::query([
						'page' => "/{$path}",
						'pageOperator' => 'contains'
					]);
				}
			}
		} else {
			$data = static::query();
		}

		if (empty($data)) {
			return [];
		}

		$dir = $asc ? 1 : -1;
		usort($data, fn ($a, $b) => match ($metric) {
			'query' => strcasecmp($a['keys'][0], $b['keys'][0]) * $dir,
			default => ($a[$metric] <=> $b[$metric]) * $dir
		});

		return array_slice($data, 0, $limit);
	}
}
