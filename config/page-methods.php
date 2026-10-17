<?php

use Kirby\Cms\Language;
use Kirby\Cms\Page;
use tobimori\Seo\Seo;

return [
	'schema' => function (string $type) {
		/** @var Page $this */
		return Seo::option('components.schema')::getInstance($type, $this);
	},
	'schemas' => function () {
		/** @var Page $this */
		return Seo::option('components.schema')::getInstances($this);
	},
	'metadata' => function (Language|null $lang = null) {
		/** @var Page $this */
		return new (Seo::option('components.meta'))($this, $lang);
	},
	'robots' => function (Language|null $lang = null) {
		/** @var Page $this */
		return $this->metadata($lang)->robots();
	},
	'indexUrl' => function () {
		/** @var Page $this */
		// Google: "fallback page for unmatched languages, especially on language/country selectors or auto-redirecting home pages."
		// https://developers.google.com/search/docs/specialty/international/localized-versions#all-method-guidelines

		// returns the index URL of the site, e.g. https://example.com/
		$kirbyUrl = (string)$this->kirby()->url('index');

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
		if (str_starts_with($siteUrl, $kirbyUrl) && strlen($siteUrl) > strlen($kirbyUrl)) {
			if (str_starts_with($thisUrl, $siteUrl)) {
				$pathAfterSite = substr($thisUrl, strlen($siteUrl));
				return "{$kirbyUrl}{$pathAfterSite}";
			}
		}

		return $thisUrl;
	}
];
