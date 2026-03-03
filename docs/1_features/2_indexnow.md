---
title: IndexNow
intro: Notify search engines when your content changes
---

Normally, search engines discover changes to your site on their own schedule, which can take days or weeks. [IndexNow](https://www.indexnow.org/) lets you skip the wait: whenever you save, publish, or move a page in Kirby, Kirby SEO notifies search engines so they can re-crawl right away.

IndexNow is supported by Bing, Yandex, Seznam, and others. Kirby SEO sends a single request to `api.indexnow.org`, which propagates to all participating search engines. Google does not support IndexNow but is not affected by it.

## How it works

IndexNow is triggered on three events:

- A page is saved
- A page changes status (e.g. draft to listed)
- A page's slug changes

Only pages that are listed and not marked as `noindex` are submitted. On local environments (localhost), no requests are sent.

## API key

IndexNow requires an API key to verify that you own the domain. Kirby SEO generates one automatically and caches it permanently. Search engines can verify it at `https://example.com/indexnow-{key}.txt`, which Kirby SEO serves as a route. You don't need to manage this yourself.

## Related URLs

By default, only the changed page itself is submitted. But when a page changes, other pages might be affected too: a blog post's parent archive shows a different excerpt, or sibling pages have updated navigation.

You can configure rules to submit related URLs along with the changed page:

```php
// site/config/config.php
return [
  'tobimori.seo' => [
    'indexnow' => [
      'rules' => [
        // when a blog post changes, also re-index its parent
        '/blog/*' => ['parent' => true],

        // when an article changes, re-index two levels of parents and specific URLs
        'article' => ['parent' => 2, 'urls' => ['/blog', '/']],

        // when a product changes, re-index siblings and all category pages
        'product' => ['parent' => true, 'siblings' => true, 'templates' => ['category']],
      ],
    ],
  ],
];
```

Rules match either by URL pattern (`/blog/*`) or by template name (`article`). Each rule can use any combination of:

- `parent`: `true` for the direct parent, or a number for how many levels up
- `children`: `true` for all descendants, or a number to limit depth
- `siblings`: `true` to include all pages at the same level
- `urls`: an array of specific URLs to submit
- `templates`: an array of template names, all pages with those templates will be submitted
