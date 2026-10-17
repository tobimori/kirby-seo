<?php

use Kirby\Cms\App;
use Kirby\Cms\Site;
use Kirby\Cms\Url;
use Kirby\Toolkit\Str;
use tobimori\Seo\Seo;

return [
	'schema' => fn (string $type) => Seo::option('components.schema')::getInstance($type),
	'schemas' => fn () => Seo::option('components.schema')::getInstances(),
	'lang' => function () {
		/** @var Site $this */
		return Seo::option('components.meta')::normalizeLocale(Seo::option('default.locale', args: [$this->homePage()]), '-');
	},
	'canonicalFor' => function (string $url, bool $useRootUrl = false, string|null $languageCode = null) {
		/** @var Site $this */
		// Determine the base URL
		$base = Seo::option('canonical.base', Seo::option('canonicalBase'));
		if (!is_string($base) || $base === '') {
			// If useRootUrl is true or this is a multilang site requesting root URL, use kirby()->url()
			if ($useRootUrl && App::instance()->multilang()) {
				$base = (string)App::instance()->url();
			} else {
				$base = $this->url($languageCode);
			}
		}

		if (Str::startsWith($url, $base)) {
			$canonicalUrl = $url;
		} else {
			$path = Url::path($url);
			$canonicalUrl = Url::to($base . '/' . $path);
		}

		$trailingSlash = Seo::option('canonical.trailingSlash', false);
		if ($trailingSlash) {
			// check if URL has a file extension (like .xml, .jpg, .pdf, etc.)
			$path = parse_url($canonicalUrl, PHP_URL_PATH) ?: '';
			$pathInfo = pathinfo($path);
			$hasExtension = !empty($pathInfo['extension'] ?? null);

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
