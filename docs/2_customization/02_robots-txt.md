---
title: Customizing robots.txt
intro: Add custom rules to your robots.txt
---

By default, Kirby SEO generates a simple `robots.txt` that allows all crawlers and blocks the Panel. If you need to add your own rules, use the `robots.content` option.

## Blocking specific bots

Some AI providers crawl websites to use the content as training data. You can block their crawlers:

```php
<?php
// site/config/config.php

return [
  'tobimori.seo' => [
    'robots' => [
      'content' => [
        'GPTBot' => [
          'Disallow' => ['/'],
        ],
        'Google-Extended' => [
          'Disallow' => ['/'],
        ],
        'CCBot' => [
          'Disallow' => ['/'],
        ],
      ],
    ],
  ],
];
```

This adds rules for each bot while keeping the default rules for all other crawlers intact.

## Custom rules for all crawlers

If you set rules for `*`, they replace the default rules entirely:

```php
'content' => [
  '*' => [
    'Allow' => ['/'],
    'Disallow' => ['/panel', '/content', '/private'],
  ],
],
```

## Mixing rules

You can combine rules for all crawlers with rules for specific bots:

```php
'content' => [
  '*' => [
    'Allow' => ['/'],
    'Disallow' => ['/panel', '/content'],
  ],
  'GPTBot' => [
    'Disallow' => ['/'],
  ],
],
```

The `Sitemap:` line is added automatically if the [sitemap module](1_features/01_sitemap) is active. You can override it with the `robots.sitemap` option.
