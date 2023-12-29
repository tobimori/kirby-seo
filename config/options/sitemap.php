<?php

use tobimori\Seo\Sitemap\SitemapIndex;

return function (SitemapIndex $sitemap) {
  $pages = site()->index()->filter(fn ($page) => $page->metadata()->robotsIndex()->toBool());

  if ($group = option('tobimori.seo.sitemap.groupByTemplate')) {
    $pages = $pages->group('intendedTemplate');
  }

  if (is_a($pages->first(), 'Kirby\Cms\Page')) {
    $pages->group(fn () => 'pages');
  }

  foreach ($pages as $group) {
    $index = $sitemap->create($group ? $group->first()->intendedTemplate()->name() : 'pages');

    foreach ($group as $page) {
      $index->createUrl($page->metadata()->canonicalUrl())
        ->lastmod($page->modified())
        ->changefreq(is_callable($changefreq = option('tobimori.seo.sitemap.changefreq')) ? $changefreq($page) : $changefreq)
        ->priority(is_callable($priority = option('tobimori.seo.sitemap.priority')) ? $priority($page) : $priority);
    }
  };
};
