---
title: Meta Cascade
intro: Understand how meta values are resolved across multiple levels
---

Kirby SEO is built with a cascading approach. Meta tags can be defined on multiple levels, and they are merged based on priority. If a value is empty on one level, it falls through to the next. This is how the plugin forms the final metadata for every page.

The default cascade, in order of priority:

1. **Page fields** (`fields`) -- Values the editor enters in the page's SEO blueprint fields. This is the highest priority: if an editor sets a meta description, it always wins.

2. **Programmatic** (`programmatic`) -- Values returned by `metaDefaults()` in [page models](2_customization/00_programmatic-content). Use this for computed defaults like generated OG images or descriptions derived from other fields.

3. **Parent** (`parent`) -- Inherited values from the parent page. If a parent page has "inherit settings" enabled for a field, its children pick up those values. Useful for giving all blog posts the same title template, for example.

4. **Site** (`site`) -- Global values from the site's SEO blueprint fields. These apply to all pages that don't have their own value set at a higher level.

5. **Options** (`options`) -- The final fallback, defined in the plugin's config defaults. These are the built-in defaults like the title template `{{ title }} - {{ site.title }}`.

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
  'site',
  'options',
],
```

## Optional: fallback fields

There is one additional cascade level that is not enabled by default:

**Fallback fields** (`fallbackFields`) -- Falls back to meta field values for Open Graph tags. When enabled, an empty `ogTitle` uses the `metaTitle` value, `ogDescription` uses `metaDescription`, and `ogTemplate` uses `metaTemplate`.

The `options` level already provides similar behavior through its defaults. Add `fallbackFields` if you want Open Graph fields to mirror editor-entered meta values before falling back to config defaults:

```php
'cascade' => [
  'fields',
  'programmatic',
  'fallbackFields',
  'parent',
  'site',
  'options',
],
```
