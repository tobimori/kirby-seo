<?php

namespace tobimori\Seo;

use Kirby\Cms\App;
use Kirby\Cms\Language;
use Kirby\Cms\Page;
use Kirby\Cms\Pages;
use Kirby\Toolkit\Str;

final class Seo
{
	/**
	 * Returns the user agent string for the plugin
	 */
	public static function userAgent(): string
	{
		return "Kirby SEO/" . App::plugin('tobimori/seo')->version() . " (+https://plugins.andkindness.com/seo)";
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
		$cached = $cache->get('indexable') ?? [];

		if (($cached['options'] ?? null) !== static::auditOptions()) {
			$cached = ['options' => static::auditOptions(), 'pages' => []];
		}

		if (!isset($cached['pages'][$code])) {
			$cached['pages'][$code] = $kirby->site()->index()->filter(fn (Page $page) => static::isIndexable($page, $language))->keys();
			$cache->set('indexable', $cached, 60);
		}

		$pages = new Pages([], $kirby->site());

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
		/** @var \tobimori\Seo\Meta $meta */
		$meta = new (static::option('components.meta'))($page, $language);

		if (static::option('robots.index')) {
			return !Str::contains($meta->robots(), 'noindex');
		}

		['field' => $field, 'source' => $source] = $meta->resolve('robotsIndex');

		// set by editors (or page models) for this page, `null` if the default is `false`
		if ($source !== null && $source !== 'options') {
			return $field->toBool();
		}

		// same as the default, without the site-wide switch
		return static::option('robots.followPageStatus') ? $page->isListed() : true;
	}

	/**
	 * Hash of the options the overview audits depend on, e.g. after enabling `debug` (which sets `robots.index` to `false`).
	 * Closures (e.g. most defaults) are encoded as empty objects, changes of their code aren't detected
	 */
	public static function auditOptions(): string
	{
		return md5(json_encode([
			static::option('robots.index'),
			static::option('robots.followPageStatus'),
			static::option('cascade'),
			static::option('default'),
		]));
	}

	public static function clearIndexable(): void
	{
		App::instance()->cache('tobimori.seo.overview')->remove('indexable');
	}

	/**
	 * Returns a plugin option
	 */
	public static function option(string $key, mixed $default = null, mixed $args = []): mixed
	{
		$option = App::instance()->option("tobimori.seo.{$key}", $default);
		if (is_callable($option)) {
			$option = $option(...$args);
		}

		return $option;
	}
}
