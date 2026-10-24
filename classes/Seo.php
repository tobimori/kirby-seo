<?php

namespace tobimori\Seo;

use Kirby\Cms\App;
use Kirby\Cms\Language;
use Kirby\Cms\Page;
use Kirby\Cms\Pages;
use Kirby\Data\Json;
use Kirby\Toolkit\I18n;
use Kirby\Toolkit\Str;

/**
 * @psalm-type IndexNowRule = array{parent?: bool|int, children?: bool|int, siblings?: bool, urls?: list<string>, templates?: list<string>}
 * @psalm-type Options = array{
 *   'components.meta': class-string<Meta>,
 *   'components.ai': class-string<Ai>,
 *   'components.indexnow': class-string<IndexNow>,
 *   'components.schema': class-string<SchemaSingleton>,
 *   'components.gsc': class-string<GoogleSearchConsole>,
 *   'components.agentic': class-string<LlmContent>,
 *   'agentic.enabled': bool,
 *   'agentic.markdown.enabled': bool,
 *   'agentic.markdown.auto': bool,
 *   'agentic.markdown.selectors': list<string>|string,
 *   'agentic.llmsTxt.enabled': bool,
 *   'links.queue': bool,
 *   'links.schedule': string|false,
 *   'links.http': bool|null,
 *   'links.ttl': int,
 *   'links.timeout': int,
 *   'links.concurrency': int,
 *   'links.hosts': list<string>|null,
 *   'cascade': list<string>,
 *   'default.robotsIndex': bool,
 *   'default.locale': string,
 *   'socialMedia': array<string, string>,
 *   'previews': list<string>,
 *   'robots.enabled': bool,
 *   'robots.active': bool,
 *   'robots.followPageStatus': bool,
 *   'robots.pageSettings': bool,
 *   'robots.index': bool,
 *   'robots.sitemap': string|null,
 *   'robots.content': array<string, array<string, list<string>>>|string|null,
 *   'robots.types': list<string>,
 *   'sitemap.enabled': bool,
 *   'sitemap.active': bool,
 *   'sitemap.redirect': bool,
 *   'sitemap.locale': string,
 *   'sitemap.changefreq': string,
 *   'sitemap.priority': string|float,
 *   'sitemap.groupByTemplate': bool,
 *   'sitemap.excludeTemplates': list<string>,
 *   'canonical.trailingSlash': bool,
 *   'ai.enabled': bool,
 *   'ai.provider': string,
 *   'indexnow.enabled': bool,
 *   'indexnow.delay': int,
 *   'indexnow.searchEngine': string,
 *   'indexnow.rules': array<string, IndexNowRule>|null,
 *   'searchConsole.enabled': bool,
 *   'searchConsole.credentials.web': array<string, mixed>|null,
 *   'searchConsole.tokenPath': string,
 *   'locale': string,
 *   'dateFormat': string|null
 * }
 */
final class Seo
{
	/**
	 * Returns the user agent string for the plugin
	 */
	public static function userAgent(): string
	{
		return 'Kirby SEO/' . (App::plugin('tobimori/seo')?->version() ?? 'dev') . ' (+https://www.andkindness.com/seo)';
	}

	/**
	 * Published pages search engines may index, which the overview audits check.
	 * Finding them needs to read all pages (e.g. thousands of form submissions), so their IDs are cached
	 * until an indexable page, the site or the options change, or for an hour for changes outside of Kirby
	 */
	public static function indexable(Language|null $language = null): Pages
	{
		$kirby = App::instance();
		$language ??= $kirby->language();
		$code = $language?->code() ?? 'default';
		$cache = $kirby->cache('tobimori.seo.overview');
		/** @var array{options?: string, pages?: array<string, list<string>>} $cached */
		$cached = $cache->get('indexable') ?? [];

		if (($cached['options'] ?? null) !== static::auditOptions()) {
			$cached = ['options' => static::auditOptions(), 'pages' => []];
		}

		if (!isset($cached['pages'][$code])) {
			$cached['pages'][$code] = $kirby
				->site()
				->index()
				->filter(static fn (Page $page) => static::isIndexable($page, $language))
				->keys();
			$cache->set('indexable', $cached, 60);
		}

		$pages = new Pages([], $kirby->site());

		/** @var string $id */
		foreach ($cached['pages'][$code] as $id) {
			if ($page = $kirby->page($id)) {
				$pages->add($page);
			}
		}

		return $pages;
	}

	/**
	 * Whether search engines may index the page. Ignores a site-wide `noindex` (e.g. on staging),
	 * so the audits still check public pages; page-specific rules (e.g. by editors or page models) apply
	 */
	public static function isIndexable(Page $page, Language|null $language = null): bool
	{
		$meta = new (static::option('components.meta'))($page, $language);

		if (static::option('robots.index')) {
			return !Str::contains($meta->robots(), 'noindex');
		}

		['field' => $field, 'source' => $source] = $meta->resolve('robotsIndex');

		// set by editors (or page models) for this page, `null` if the default is `false`
		if ($source !== null && $source !== 'options') {
			return $field->toBool();
		}

		return static::isIndexableByDefault($page);
	}

	/**
	 * Default of `robotsIndex` without the site-wide `robots.index` switch
	 */
	public static function isIndexableByDefault(Page $page): bool
	{
		if ($page->isErrorPage()) {
			return false;
		}

		return static::option('robots.followPageStatus') ? $page->isListed() : true;
	}

	/**
	 * Hash of the options the overview audits depend on, e.g. after enabling `debug` (which sets `robots.index` to `false`).
	 * Closures (e.g. most defaults) are encoded as empty objects, changes of their code aren't detected
	 */
	public static function auditOptions(): string
	{
		return md5(Json::encode([
			static::option('robots.index'),
			static::option('robots.followPageStatus'),
			static::option('cascade'),
			static::option('default')
		]));
	}

	/**
	 * Translation of the key as a string, or the key itself if the translation is missing
	 */
	public static function translate(string $key): string
	{
		$translation = I18n::translate($key);

		return is_string($translation) ? $translation : $key;
	}

	public static function clearIndexable(): void
	{
		App::instance()->cache('tobimori.seo.overview')->remove('indexable');
	}

	/**
	 * Returns a plugin option
	 *
	 * @template K of string
	 * @param K $key
	 * @return (K is key-of<Options> ? Options[K] : mixed)
	 */
	public static function option(string $key, mixed $default = null, iterable $args = []): mixed
	{
		$option = App::instance()->option("tobimori.seo.{$key}", $default);
		if (is_callable($option)) {
			$option = $option(...$args);
		}

		return $option;
	}
}
