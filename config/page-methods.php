<?php

use Kirby\Cms\Language;
use tobimori\Seo\SchemaSingleton;
use tobimori\Seo\Seo;

return [
	'schema' => fn ($type) => Seo::option('components.schema')::getInstance($type, $this),
	'schemas' => fn () => Seo::option('components.schema')::getInstances($this),
	'metadata' => fn (?Language $lang = null) => new (Seo::option('components.meta'))($this, $lang),
	'robots' => fn (?Language $lang = null) => $this->metadata($lang)->robots(),
	'indexUrl' => function () {
		// Google: "fallback page for unmatched languages, especially on language/country selectors or auto-redirecting home pages."
		// https://developers.google.com/search/docs/specialty/international/localized-versions#all-method-guidelines

		// returns the index URL of the site, e.g. https://example.com/
		$kirbyUrl = $this->kirby()->url('index');

		$defaultLang = $this->kirby()->defaultLanguage()?->code();
		// returns the site URL, e.g. https://example.com/en
		// we have to request the default language so we don't get localized slugs
		$siteUrl = $this->site()->url($defaultLang);

		// returns the full URL of the current page in the default language, e.g. https://example.com/en/about
		// again, request default language otherwise there is a mismatch in language prefix between the site URL and the current page URL
		$thisUrl = $this->url($defaultLang);

		// remove the part form the URL that is specific to the 'site'
		// this is usually the language code prefix
		// https://example.com/en/ + https://example.com/en/about -> https://example.com/about
		if (strpos($siteUrl, $kirbyUrl) === 0 && strlen($siteUrl) > strlen($kirbyUrl)) {
			if (strpos($thisUrl, $siteUrl) === 0) {
				$pathAfterSite = substr($thisUrl, strlen($siteUrl));
				return "{$kirbyUrl}{$pathAfterSite}";
			}
		}

		return $thisUrl;
	},
];
