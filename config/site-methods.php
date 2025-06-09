<?php

use Kirby\Http\Url;
use Kirby\Toolkit\Str;
use tobimori\Seo\SchemaSingleton;
use tobimori\Seo\Seo;

return [
	'schema' => fn($type) => SchemaSingleton::getInstance($type),
	'schemas' => fn() => SchemaSingleton::getInstances(),
	'lang' => fn() => Str::replace(Seo::option('default.lang')($this->homePage()), '_', '-'),
	'canonicalFor' => function (string $url) {
		$base = Seo::option('canonical.base', Seo::option('canonicalBase', $this->url()));
		if (Str::startsWith($url, $base)) {
			$canonicalUrl = $url;
		} else {
			$path = Url::path($url);
			$canonicalUrl = url($base . '/' . $path);
		}

		$trailingSlash = Seo::option('canonical.trailingSlash', false);
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
