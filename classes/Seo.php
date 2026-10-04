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
	 * until an indexable page or the site changes, or for an hour for changes outside of Kirby
	 */
	public static function indexable(Language|null $language = null): Pages
	{
		$kirby = App::instance();
		$language ??= $kirby->language();
		$code = $language?->code() ?? 'default';
		$cache = $kirby->cache('tobimori.seo.overview');
		$cached = $cache->get('indexable') ?? [];

		if (!isset($cached[$code])) {
			$cached[$code] = $kirby->site()->index()->filter(fn (Page $page) => static::isIndexable($page, $language))->keys();
			$cache->set('indexable', $cached, 60);
		}

		$pages = new Pages([], $kirby->site());

		foreach ($cached[$code] as $id) {
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
		if (!static::option('robots.enabled')) {
			return true;
		}

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
