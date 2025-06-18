<?php

namespace tobimori\Seo;

use Kirby\Cms\App;

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
