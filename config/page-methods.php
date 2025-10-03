<?php

use Kirby\Cms\Language;
use tobimori\Seo\Meta;
use tobimori\Seo\SchemaSingleton;

return [
	'schema' => fn ($type) => SchemaSingleton::getInstance($type, $this),
	'schemas' => fn () => SchemaSingleton::getInstances($this),
	'metadata' => fn (?Language $lang = null) => new Meta($this, $lang),
	'robots' => fn (?Language $lang = null) => $this->metadata($lang)->robots(),
	'indexUrl' => function () {
		// Google: "fallback page for unmatched languages, especially on language/country selectors or auto-redirecting home pages."
		// https://developers.google.com/search/docs/specialty/international/localized-versions#all-method-guidelines
		$kirbyUrl = $this->kirby()->url('index');
		$siteUrl = $this->site()->url();
		$thisUrl = $this->url($this->kirby()->defaultLanguage()?->code());

		if (strpos($siteUrl, $kirbyUrl) === 0 && strlen($siteUrl) > strlen($kirbyUrl)) {
			if (strpos($thisUrl, $siteUrl) === 0) {
				$pathAfterSite = substr($thisUrl, strlen($siteUrl));
				return $kirbyUrl . $pathAfterSite;
			}
		}

		return $thisUrl;
	},
];
