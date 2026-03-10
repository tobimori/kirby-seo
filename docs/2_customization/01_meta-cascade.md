---
title: Meta Cascade
intro: Understand how meta values are resolved across multiple levels
---

Kirby SEO is built with a cascading approach. Meta tags can be defined on multiple levels, and they are merged based on priority. If a value is empty on one level, it falls through to the next. This is how the plugin forms the final metadata for every page.

The default cascade, in order of priority:

1. **Page fields** (`fields`) -- Values the editor enters in the page's SEO blueprint fields. This is the highest priority: if an editor sets a meta description, it always wins.

2. **Programmatic** (`programmatic`) -- Values returned by `metaDefaults()` in [page models](2_customization/00_programmatic-content). Use this for computed defaults like generated OG images or descriptions derived from other fields.

3. **Parent** (`parent`) -- Inherited values from the parent page. If a parent page has "inherit settings" enabled for a field, its children pick up those values. Useful for giving all blog posts the same title template, for example.

4. **Fallback fields** (`fallbackFields`) -- Falls back to meta field values for Open Graph tags. If no `ogDescription` is set, the page's `metaDescription` is used instead.

5. **Site** (`site`) -- Global values from the site's SEO blueprint fields. These apply to all pages that don't have their own value set at a higher level.

6. **Options** (`options`) -- The final fallback, defined in the plugin's config defaults. These are the built-in defaults like the title template `{{ title }} - {{ site.title }}`.

## Configuring the cascade

The cascade order is configurable in your `config.php`. You can remove levels, reorder them, or add optional ones:

```php
<?php
// site/config/config.php

return [
  'tobimori.seo' => [
    'cascade' => [
      'fields',
      'programmatic',
      'parent',
      'fallbackFields',
      'site',
      'options',
    ],
  ],
];
```

Remove an entry to skip that level entirely. For example, to disable parent inheritance:

```php
'cascade' => [
  'fields',
  'programmatic',
  'fallbackFields',
  'site',
  'options',
],
```

## Restore the 1.x behavior

In 1.x, if you set an `ogDescription` at the site level, it applied to every page, even pages that had their own `metaDescription`. The page-specific description never made it into the Open Graph tags.

In 2.x, the `fallbackFields` level sits between `parent` and `site`, so a page's `metaDescription` is used as `ogDescription` before site-wide Open Graph values are reached.

To restore the 1.x behavior, remove `fallbackFields` from the cascade:

```php
'cascade' => [
  'fields',
  'programmatic',
  'parent',
  'site',
  'options',
],
```

<details>
<summary>If you used <code>fallbackFields</code> with additional mappings in 1.x</summary>

In 1.x, `fallbackFields` also mapped `ogTemplate` to `metaTemplate`. If you relied on this, you can restore it by extending the `Meta` class and overriding the `FALLBACK_MAP` constant:

```php
<?php

use tobimori\Seo\Meta;

class MyMeta extends Meta
{
  public const FALLBACK_MAP = [
    'ogDescription' => 'metaDescription',
    'ogTemplate' => 'metaTemplate',
  ];
}
```

Then register your class in the config. See [Extending the Plugin](2_customization/11_plugin-extensions) for details.

</details>
