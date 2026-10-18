---
title: Customizing robots.txt
intro: Add custom rules to your robots.txt
---

By default, Kirby SEO generates a simple `robots.txt` that allows all crawlers and blocks the Panel. If you need to add your own rules, use the `robots.content` option.

Any non-empty `robots.content` value replaces all default content. Include the general `User-agent: *`, `Allow: /`, and `Disallow: /panel` rules when you want to keep them. The `Sitemap:` line is still appended automatically.

## Blocking specific bots

Some AI providers crawl websites to use the content as training data. You can block their crawlers:

```php
<?php
// site/config/config.php

return [
  'tobimori.seo' => [
    'robots' => [
      'content' => [
        '*' => [
          'Allow' => ['/'],
          'Disallow' => ['/panel'],
        ],
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

## Custom rules for all crawlers

Include the general rules together with any additional paths:

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
