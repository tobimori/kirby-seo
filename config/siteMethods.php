<?php

use Kirby\Http\Url;
use Kirby\Toolkit\Str;
use tobimori\Seo\SchemaSingleton;

return [
	'schema' => fn ($type) => SchemaSingleton::getInstance($type),
	'schemas' => fn () => SchemaSingleton::getInstances(),
	'lang' => fn () => Str::replace(option('tobimori.seo.default.lang')($this->homePage()), '_', '-'),
	'canonicalFor' => function (string $url) {
		$base = option('tobimori.seo.canonicalBase');
		if (is_callable($base)) {
			$base = $base($url);
		}

		if ($base === null) {
			$base = $this->url(); // graceful fallback to site url
		}

		if (Str::startsWith($url, $base)) {
			return $url;
		}

		$path = Url::path($url);
		return url($base . '/' . $path);
	}
];
