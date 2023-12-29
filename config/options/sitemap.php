<?php

use tobimori\Seo\Sitemap\SitemapIndex;

return function (SitemapIndex $sitemap) {
  $index = $sitemap->create();

  foreach (site()->index() as $page) {
    $meta = $page->metadata();
    if (!$meta->robotsIndex()->toBool()) continue;

    $index->createUrl($meta->canonicalUrl())
      ->lastmod($page->modified())
      ->changefreq('weekly')
      ->priority('0.5');
  };
};
