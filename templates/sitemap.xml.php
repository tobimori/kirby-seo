<?php

use tobimori\Seo\Sitemap\SitemapIndex;

// render first, so an invalid index throws before the response type is set
$xml = SitemapIndex::instance()->render($page);
$kirby->response()->type('text/xml');

echo $xml;
