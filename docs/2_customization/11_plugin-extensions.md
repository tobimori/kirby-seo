---
title: Extending the Plugin
intro: Replace built-in classes with your own
---

Kirby SEO uses a component system similar to [Kirby's own](https://getkirby.com/docs/reference/plugins/components). The components listed below can be swapped out for custom classes. This lets you change how the plugin works without forking it.

The built-in components are:

| Key        | Default class                      | Handles                           |
| ---------- | ---------------------------------- | --------------------------------- |
| `meta`     | `tobimori\Seo\Meta`                | Meta tag generation and cascading |
| `ai`       | `tobimori\Seo\Ai`                  | AI Assist provider management     |
| `indexnow` | `tobimori\Seo\IndexNow`            | IndexNow ping requests            |
| `schema`   | `tobimori\Seo\SchemaSingleton`     | Schema.org structured data store  |
| `gsc`      | `tobimori\Seo\GoogleSearchConsole` | Google Search Console integration |
| `agentic`  | `tobimori\Seo\LlmContent`          | LLM-friendly content              |

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

Calls to `$page->metadata()` and other plugin internals resolve `components.meta` from this config and instantiate `MyMeta`. Look at the built-in classes in the plugin's `classes/` directory to see what methods are available to override.
