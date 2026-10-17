<?php

use Kirby\Cms\App;
use Kirby\Toolkit\A;
use tobimori\Seo\Seo;

if ($content = Seo::option('robots.content')) {
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

// Use the sitemap of the plugin if none is set
$sitemap = Seo::option('robots.sitemap');
if (!$sitemap && Seo::option('sitemap.active')) {
	$sitemap = App::instance()->site()->canonicalFor('/sitemap.xml', true);
}

if ($sitemap) {
	echo "\n\nSitemap: {$sitemap}";
}
