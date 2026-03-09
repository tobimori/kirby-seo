---
title: Extending the Plugin
intro: Replace built-in classes with your own
---

Kirby SEO uses a component system similar to [Kirby's own](https://getkirby.com/docs/reference/plugins/components). Every major class in the plugin can be swapped out for a custom one. This lets you change how the plugin works without forking it.

The built-in components are:

| Key        | Default class                      | Handles                           |
| ---------- | ---------------------------------- | --------------------------------- |
| `meta`     | `tobimori\Seo\Meta`                | Meta tag generation and cascading |
| `ai`       | `tobimori\Seo\Ai`                  | AI Assist provider management     |
| `indexnow` | `tobimori\Seo\IndexNow`            | IndexNow ping requests            |
| `schema`   | `tobimori\Seo\SchemaSingleton`     | Schema.org structured data store  |
| `gsc`      | `tobimori\Seo\GoogleSearchConsole` | Google Search Console integration |

To replace a component, create a class that extends the original. For example, to customize meta tag output, extend the `Meta` class:

```php
<?php
// site/plugins/my-seo/index.php

use tobimori\Seo\Meta;

class MyMeta extends Meta
{
  // override any method you need
}
```

Then register your class in the config:

```php
<?php
// site/config/config.php

return [
  'tobimori.seo' => [
    'components' => [
      'meta' => MyMeta::class,
    ],
  ],
];
```

The rest of the plugin picks up your class automatically. Page methods, hooks, routes, and sections all resolve components through the config, so your class is used everywhere the original would have been. Look at the built-in classes in `site/plugins/kirby-seo/classes/` to see what methods are available to override.
