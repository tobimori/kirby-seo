---
title: Upgrading from 1.x
intro: Update a Kirby SEO 1.x site for Kirby SEO 2.0
---

Kirby SEO 2.0 requires Kirby 5 and PHP 8.3 or later. Follow [Kirby's upgrade guide](https://getkirby.com/docs/guide/updates/update-to-v5) for the Kirby update. Kirby SEO 1.x does not run on Kirby 5, and Kirby SEO 2.0 does not run on Kirby 4. Update both at the same time.

Your content, blueprints and the `seo/head` and `seo/schemas` snippets continue to work without changes. Most sites only need a few updates.

## Rename `lang` options to `locale`

The language-related option names changed in 2.0:

- `lang` → `locale`
- `sitemap.lang` → `sitemap.locale`
- `default.lang` → `default.locale`

Rename any of these options in the `tobimori.seo` section of `site/config/config.php`:

```php
return [
  'tobimori.seo' => [
    'locale' => 'de_DE',
    'sitemap' => [
      'locale' => 'de',
    ],
  ],
];
```

The old names are ignored without an error. For example, a German site that still uses `'lang' => 'de_DE'` outputs the default `og:locale` value `en_US`.

## Activate a license on public servers

Kirby SEO 1.x was available under the MIT license. Kirby SEO 2.0 requires a license on public servers. Local development does not require activation.

Follow [Purchase license & activate your installation](0_getting-started/0_quickstart#purchase-license--activate-your-installation) when the site goes live.

## Rename deprecated options

These option names still work in 2.0, but are deprecated:

- `canonicalBase` → `canonical.base`
- `robots.active` → `robots.enabled`
- `sitemap.active` → `sitemap.enabled`

Rename them when you update your config. See the [options reference](3_reference/0_options) for all available options.

## Install Schema.org support when you use it

The `spatie/schema-org` package is no longer installed with Kirby SEO. Install it separately on sites that output Schema.org data:

```bash
composer require "spatie/schema-org:^5.0"
```

Version 5 of the package requires PHP 8.4. Without the package, Kirby SEO does not output JSON-LD, and `$page->schema()`, `$site->schema()` and `schema()` return `null`. Chained calls such as `$page->schema('Article')->headline(...)` then fail.

Remove `schema()` calls on sites that do not use Schema.org. See [Schema.org](2_customization/08_schema-org) for setup and usage.

## Remove obsolete Twitter options

Kirby SEO 2.0 no longer uses these options:

- `twitter`
- `default.twitterCardType`
- `default.twitterCreator`

It does not output `twitter:*` tags. X removed its card documentation and reads the Open Graph tags instead.

The Panel fields "Twitter Card Type" and "Author @username on Twitter" are also gone. Existing values remain in your content files and are ignored. You do not need to add Twitter tags back.

## Check the Open Graph description fallback

In 1.x, a page without its own Open Graph description used the site's Open Graph description. In 2.0, the page's meta description has priority.

To keep the 1.x behavior, follow [Restore the 1.x behavior](2_customization/01_meta-cascade#restore-the-1x-behavior) in the Meta Cascade documentation.

## Update custom PHP calls

`$page->metadata()` and `$page->robots()` now take a `Kirby\Cms\Language` object instead of a language code. Passing a string throws a `TypeError`:

```php
// 1.x
$page->metadata('de');
$page->robots('de');

// 2.0
$language = $kirby->language('de');
$page->metadata($language);
$page->robots($language);
```

In [custom sitemap generators](2_customization/05_sitemap), `lastmod()` now takes a Unix timestamp instead of a date string:

```php
// 1.x
$url->lastmod(date('c', $page->modified()));

// 2.0
$url->lastmod($page->modified());
```
