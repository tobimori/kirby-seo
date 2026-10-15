<?php

use tobimori\Seo\Sitemap\SitemapIndex;

/**
 * @var \Kirby\Cms\App $kirby
 * @var \Kirby\Cms\Page $page
 */

// render first, so an invalid index throws before the response type is set
$xml = SitemapIndex::instance()->render($page);
$kirby->response()->type('text/xml');

echo $xml;
