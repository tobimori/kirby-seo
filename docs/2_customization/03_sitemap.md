---
title: Customizing the Sitemap
intro: Fine-tune the built-in sitemap or replace it entirely
---

The built-in sitemap generator has a few options to adjust its behavior. For most sites, these are enough. If you need full control, you can replace the generator with your own.

## Excluding templates

By default, only the `error` template is excluded. To exclude more templates:

```php
<?php
// site/config/config.php

return [
  'tobimori.seo' => [
    'sitemap' => [
      'excludeTemplates' => ['error', 'redirect', 'internal'],
    ],
  ],
];
```

## Grouping by template

By default, all pages end up in a single sitemap. If you have many pages, you can split them into separate sitemaps per template. This creates a sitemap index at `/sitemap.xml` with links to `/sitemap-blog.xml`, `/sitemap-product.xml`, etc.

```php
'sitemap' => [
  'groupByTemplate' => true,
],
```

## Change frequency and priority

Both `changefreq` and `priority` accept a static value or a callable:

```php
'sitemap' => [
  'changefreq' => 'daily',
  'priority' => fn (Page $page) => $page->isHomePage() ? 1.0 : 0.5,
],
```

The default `changefreq` is `weekly`. The default `priority` is calculated from page depth: the homepage gets `1.0`, each level deeper subtracts `0.2`, down to `0.2`.

## Writing your own generator

If the options above aren't enough, you can replace the entire sitemap generator. The `generator` option takes a callable that receives a `SitemapIndex` instance. Here's a minimal example:

```php
<?php

use tobimori\Seo\Sitemap\SitemapIndex;

return [
  'tobimori.seo' => [
    'sitemap' => [
      'generator' => function (SitemapIndex $sitemap) {
        $index = $sitemap->create('pages');

        foreach (site()->index()->listed() as $page) {
          $index->createUrl($page->url())
            ->lastmod($page->modified())
            ->changefreq('weekly')
            ->priority(0.8);
        }
      },
    ],
  ],
];
```

`$sitemap->create('key')` creates a sitemap group. `$index->createUrl($url)` adds a URL entry, and you can chain `->lastmod()`, `->changefreq()`, `->priority()`, and `->alternates()` on it.

The built-in generator does more: it filters by robots settings, respects `excludeTemplates`, handles `groupByTemplate`, and adds hreflang links for multilingual sites. You can find its source in `config/options/sitemap.php` as a reference for your own.
