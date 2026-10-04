<?php

namespace tobimori\Seo;

use Kirby\Cms\App;
use Kirby\Data\Json;
use Kirby\Exception\InvalidArgumentException;
use Kirby\Filesystem\F;
use Kirby\Http\Remote;
use Kirby\Plugin\License as KirbyLicense;
use Kirby\Plugin\LicenseStatus;
use Kirby\Plugin\Plugin;
use Kirby\Toolkit\A;
use Kirby\Toolkit\Str;
use Throwable;

/**
 * Kirby SEO License implementation for Kirby 5
 *
 * If you're here to crack the plugin, please buy a license instead.
 * I'm an independent developer and this plugin helps fund my open-source work as well.
 * https://www.andkindness.com/buy?plugin=seo
 *
 * If you're unable to afford a license, or you encounter any issues with
 * the license validation being too strict, please let me know at support@andkindness.com.
 * I'm happy to help.
 *
 * Licenses are read from two files next to Kirby's own license file:
 * - `.tm-licenses` is shared by all my plugins and written by the Panel activation.
 *   It holds a list of signed licenses, one per plugin and domain. Each plugin ships
 *   its own copy of this class, so keep the file format in sync with the other
 *   plugins and only change entries of this plugin.
 * - `.seo-license` is a license file downloaded from the account area and added
 *   manually. It holds one signed license or a list of them and is never written.
 *
 * Licenses belong to a plugin by the prefix of their signed license key.
 *
 * Licenses are checked offline. Licenses with an expiry date are reissued by the
 * license server shortly before they expire. Activated licenses without an expiry date
 * (issued before expiry dates existed) are reissued once to get one. Manual licenses
 * without an expiry date are permanent. Reissued copies are stored in `.tm-licenses`.
 */
final class License extends KirbyLicense
{
	// license keys of this plugin start with this prefix, e.g. `KS-BAS-…`
	private const PREFIX = 'KS-';
	private const FILE = '.tm-licenses';
	private const MANUAL_FILE = '.seo-license';
	private const BASE = 'https://plugins.andkindness.com/licenses/';

	// the order of the fields is part of the signature, `expires` is signed last if it exists
	private const SIGNED_FIELDS = ['license', 'plugin', 'edition', 'allowOfflineUse', 'purchasedOn', 'assignedUrl', 'email'];

	// seconds before the expiry date to start reissuing
	private const REISSUE_BEFORE = 7 * 24 * 60 * 60;

	// minutes to wait before the next reissue attempt after a failure
	private const REISSUE_BACKOFF = [5, 60, 180, 720, 1440];

	private array|null $data;

	// whether the license is from the manual license file
	private bool $manual;

	public function __construct(
		protected Plugin $plugin
	) {
		$this->name = 'Kirby SEO License';
		$this->link = 'https://www.andkindness.com/legal/license-agreement';
		[$this->data, $this->manual] = static::find(App::instance()->system()->indexUrl());
		$this->reissue();

		if (($state = $this->state()) === 'active') {
			$this->status = LicenseStatus::from('active');
			return;
		}

		$status = LicenseStatus::from(App::instance()->system()->isLocal() ? 'demo' : 'missing');
		$this->status = new LicenseStatus(
			value: $status->value(),
			icon: $state === 'missing' ? $status->icon() : 'alert',
			label: $state === 'missing' ? $status->label() : t("seo.license.status.{$state}"),
			theme: $state === 'missing' ? $status->theme() : 'negative',
			dialog: 'seo/activate'
		);
	}

	public function isValid(): bool
	{
		return $this->data !== null && static::isActive($this->data);
	}

	/**
	 * Returns `active`, `revoked` (moved to another domain or revoked by the license server),
	 * `expired` (could not be reissued in time) or `missing`
	 */
	public function state(): string
	{
		return match (true) {
			$this->isValid() => 'active',
			($this->data['revoked'] ?? false) === true => 'revoked',
			$this->data !== null && static::isSigned($this->data) => 'expired',
			default => 'missing',
		};
	}

	/**
	 * Normalizes the domain like the license server and Kirby: testing subdomains
	 * and `www.` are removed only for installations at the root of the domain
	 */
	public static function normalizeUrl(string $url): string
	{
		$url = rtrim(preg_replace('#^https?://#', '', Str::lower(trim($url))), '/');

		return str_contains($url, '/') ? $url : preg_replace('/^(?:www|dev|test|staging)\./', '', $url);
	}

	/**
	 * Downloads the license for the current domain and adds it to the shared file
	 */
	public static function activate(string $email, string $license): void
	{
		if (!static::isOwn(['license' => $license])) {
			throw new InvalidArgumentException(t('seo.license.error.plugin'));
		}

		try {
			$response = static::request(Str::lower($license) . '/download', [
				'email' => $email,
				'url' => static::normalizeUrl(App::instance()->system()->indexUrl()),
				// older plugin versions can't verify licenses with an expiry date
				'expires' => true,
			]);
		} catch (Throwable) {
			throw new InvalidArgumentException(t('seo.license.error.server'));
		}

		$data = $response->code() === 200 ? $response->json() : null;
		if (!is_array($data)) {
			throw new InvalidArgumentException(t(match ($response->code()) {
				404 => 'seo.license.error.key',
				403 => 'seo.license.error.forbidden',
				default => 'seo.license.error.download',
			}));
		}

		if (!static::isOwn($data)) {
			throw new InvalidArgumentException(t('seo.license.error.plugin'));
		}

		if (!static::isSigned($data)) {
			throw new InvalidArgumentException(t('seo.license.error.download'));
		}

		static::write($data);
	}

	/**
	 * Gets a new copy of the license from the license server if it expires soon,
	 * or if it was activated before expiry dates existed.
	 * A failed attempt is stored in the unsigned `failures` and `checked` fields of the license.
	 */
	private function reissue(): void
	{
		if (
			$this->data === null
			|| ($this->data['revoked'] ?? false) === true
			// licenses for sites without internet access are never reissued
			|| $this->data['allowOfflineUse'] === true
			|| !static::isSigned($this->data)
		) {
			return;
		}

		if (isset($this->data['expires'])
			? strtotime($this->data['expires']) - time() > self::REISSUE_BEFORE
			: $this->manual
		) {
			return;
		}

		$failures = (int)($this->data['failures'] ?? 0);
		$backoff = self::REISSUE_BACKOFF[min($failures, count(self::REISSUE_BACKOFF)) - 1] ?? 0;
		if (time() - strtotime($this->data['checked'] ?? '') < $backoff * 60) {
			return;
		}

		try {
			$response = static::request(Str::lower($this->data['license']) . '/reissue', [
				'url' => $this->data['assignedUrl'],
			]);
		} catch (Throwable) {
			$response = null;
		}

		$license = $response?->code() === 200 ? $response->json() : null;

		$this->data = match (true) {
			// the license server issued a new copy of the license
			is_array($license)
				&& static::isOwn($license)
				&& static::isSigned($license)
				&& $license['license'] === $this->data['license'] => $license,
			// the license was moved to another domain or revoked,
			// keep it marked as revoked so it also blocks its copy in the manual file
			$response?->code() === 410 => [...$this->data, 'revoked' => true],
			// the license server is not reachable or failed, try again later
			default => [
				...$this->data,
				'failures' => $failures + 1,
				'checked' => date(DATE_ATOM),
			],
		};

		try {
			static::write($this->data);
		} catch (Throwable) {
			// the config folder is not writable, use the result for this request only
		}
	}

	private static function request(string $path, array $data): Remote
	{
		return Remote::post(self::BASE . $path, [
			'headers' => [
				'Content-Type' => 'application/json',
				'Accept' => 'application/json',
			],
			'data' => Json::encode($data),
		]);
	}

	private static function path(string $file): string
	{
		return dirname(App::instance()->root('license')) . '/' . $file;
	}

	/**
	 * Returns all licenses from a license file, including those of other plugins
	 */
	private static function read(string $file, bool $strict = false): array
	{
		try {
			$licenses = F::exists($path = static::path($file)) ? Json::read($path) : [];
		} catch (Throwable $error) {
			if ($strict) {
				throw $error;
			}

			return [];
		}

		// a manual license file can hold a single license
		if (isset($licenses['license'])) {
			$licenses = [$licenses];
		}

		return array_values(array_filter($licenses, 'is_array'));
	}

	/**
	 * Returns the license of this plugin for the given domain and whether it is from the manual file,
	 * preferring an active license from the shared file over the manual file
	 *
	 * @return array{0: array|null, 1: bool}
	 */
	private static function find(string $url): array
	{
		$shared = A::find(static::read(self::FILE), fn (array $license) => static::isFor($license, $url));
		$manual = A::find(static::read(self::MANUAL_FILE), fn (array $license) => static::isFor($license, $url));

		// a revoked license also blocks its copy in the manual file
		if (($shared['revoked'] ?? false) === true && ($manual['license'] ?? null) === $shared['license']) {
			return [$shared, false];
		}

		if ($manual !== null && ($shared === null || (!static::isActive($shared) && static::isActive($manual)))) {
			return [$manual, true];
		}

		return [$shared, false];
	}

	/**
	 * Adds the license to the shared file and replaces the license
	 * of this plugin for the same domain and other copies of the same license key.
	 * Fails if the shared file can't be read, so licenses of other plugins are never lost.
	 */
	private static function write(array $license): void
	{
		$licenses = array_filter(
			static::read(self::FILE, strict: true),
			fn (array $entry) => !static::isFor($entry, $license['assignedUrl'])
				&& ($entry['license'] ?? null) !== $license['license']
		);

		Json::write(static::path(self::FILE), [...array_values($licenses), $license]);
	}

	/**
	 * Checks that the license is signed, not expired and not revoked
	 */
	private static function isActive(array $license): bool
	{
		return ($license['revoked'] ?? false) !== true
			&& static::isSigned($license)
			&& (!isset($license['expires']) || strtotime($license['expires']) > time());
	}

	private static function isOwn(array $license): bool
	{
		return is_string($key = $license['license'] ?? null) && str_starts_with(Str::upper(trim($key)), self::PREFIX);
	}

	private static function isFor(array $license, string $url): bool
	{
		return static::isOwn($license)
			&& static::normalizeUrl($license['assignedUrl'] ?? '') === static::normalizeUrl($url);
	}

	/**
	 * Checks that the license is complete and signed by the license server
	 */
	private static function isSigned(array $license): bool
	{
		$signed = [];
		$fields = isset($license['expires']) ? [...self::SIGNED_FIELDS, 'expires'] : self::SIGNED_FIELDS;
		foreach ($fields as $field) {
			if (($signed[$field] = $license[$field] ?? null) === null) {
				return false;
			}
		}

		return is_string($signature = $license['signature'] ?? null) && openssl_verify(
			Json::encode($signed),
			base64_decode($signature),
			openssl_pkey_get_public('file://' . dirname(__DIR__) . '/public.pem'),
			'RSA-SHA256'
		) === 1;
	}
}
