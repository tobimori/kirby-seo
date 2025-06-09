<?php

use Kirby\Http\Url;
use Kirby\Toolkit\Str;
use tobimori\Seo\SchemaSingleton;

return [
	'schema' => fn($type) => SchemaSingleton::getInstance($type),
	'schemas' => fn() => SchemaSingleton::getInstances(),
	'lang' => fn() => Str::replace(option('tobimori.seo.default.lang')($this->homePage()), '_', '-'),
	'canonicalFor' => function (string $url) {
		$base = option('tobimori.seo.canonical.base') ?? option('tobimori.seo.canonicalBase');
		if (is_callable($base)) {
			$base = $base($url);
		}

		if ($base === null) {
			$base = $this->url(); // graceful fallback to site url
		}

		if (Str::startsWith($url, $base)) {
			$canonicalUrl = $url;
		} else {
			$path = Url::path($url);
			$canonicalUrl = url($base . '/' . $path);
		}

		$trailingSlash = option('tobimori.seo.canonical.trailingSlash', false);
		if ($trailingSlash) {
			// check if URL has a file extension (like .xml, .jpg, .pdf, etc.)
			$pathInfo = pathinfo(parse_url($canonicalUrl, PHP_URL_PATH));
			$hasExtension = isset($pathInfo['extension']) && !empty($pathInfo['extension']);

			// Only add trailing slash if:
			// - URL doesn't already have one
			// - URL doesn't have a file extension
			// - URL isn't just the base domain
			if (!Str::endsWith($canonicalUrl, '/') && !$hasExtension && $canonicalUrl !== $base) {
				$canonicalUrl .= '/';
			}
		}

		return $canonicalUrl;
	}
];
