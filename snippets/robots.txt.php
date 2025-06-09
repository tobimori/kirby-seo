<?php

use Kirby\Toolkit\A;
use tobimori\Seo\Seo;

if ($content = Seo::option('robots.content')) {
	if (is_callable($content)) {
		$content = $content();
	}

	if (is_array($content)) {
		$str = [];

		foreach ($content as $ua => $data) {
			$str[] = 'User-agent: ' . $ua;
			foreach ($data as $type => $values) {
				foreach ($values as $value) {
					$str[] = $type . ': ' . $value;
				}
			}
		}

		$content = A::join($str, PHP_EOL);
	}

	echo $content;
} else {
	// output default
	echo "User-agent: *\n";

	$index = Seo::option('robots.index');

	if ($index) {
		echo 'Allow: /';
		echo "\nDisallow: /panel";
	} else {
		echo 'Disallow: /';
	}
}

if (($sitemap = Seo::option('robots.sitemap')) || ($sitemapModule = Seo::option('sitemap.active'))) {

	// Use default sitemap if none is set
	if (!$sitemap && $sitemapModule) {
		$sitemap = site()->canonicalFor('/sitemap.xml', true);
	}

	// Check again, so falsy values can't be used
	if ($sitemap) {
		echo "\n\nSitemap: {$sitemap}";
	}
}
