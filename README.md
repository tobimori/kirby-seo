![Kirby SEO Banner](/.github/banner.png)

<h1 align="center">Kirby SEO</h1>
<p align="center">SEO for Kirby CMS – done right.</p>

---

## Features

- 🔎 All-in-one SEO and meta solution
- 🪜 The Meta Cascade: Intelligently merge meta data from multiple sources
- 🎛 Completely configurable: Disable features you don't need
- 💻 Simple Panel UI with previews for Google, Twitter, Facebook & Co.
- 📮 [Schema.org](https://schema.org/) support with fluent classes
- 🤖 Automatic Robots rule generation, based on page status
- [_to be extended_](#roadmap)

## Requirements

- Kirby 4.0+ (Kirby 3 support is available for <=0.3.4)
- PHP 8.1+

## How it works - The Meta Cascade 🪜

Kirby SEO is built with a cascading approach in mind. This means that you can define meta tags on multiple levels, and they will be merged together based on their priority:

1. **Page fields**: The default page blueprint that allows you to specify most options
2. **Programmatic content**: With Page models you can programmatically set meta tags like you want
3. **Inherited fields from parent page**: Most fields can be configured to inherit to child pages
4. **Site globals**: The site blueprint allows you to set global defaults for all pages
5. **Options**: The last fallback is defined in the plugin options

If any setting is left empty, it will fallback to the next level. In this way, the plugin provides _cascades_ to form the final meta data.

## Usage

### Installation

#### Recommended: Composer

```
composer require tobimori/kirby-seo
```

#### Manual installation

Download and copy this repository to `/site/plugins/kirby-seo`, or apply this repository as Git submodule.

### Getting started

Add the blueprint tabs to your blueprints.

```yaml
# site/blueprints/site.yml
tabs:
  content:
    fields:
      # your fields
  seo: seo/site
```

```yaml
# site/blueprints/pages/default.yml (or any other template)
tabs:
  content:
    fields:
      # your fields
  seo: seo/page
```

Add the meta snippet to your templates.

```php
// site/templates/default.php
<html lang="<?= $site->lang() ?>">
  <head>
    <?php snippet('seo/head'); ?>
  </head>
  <body>
    [...]
    <?php snippet('seo/schemas'); ?>
  </body>
</html>
```

..and start defining your meta data in panel.

#### Single-Language Setup

If you're using a single-language setup, it's important to define the language code of your website in the config file:

```php
// config.php
return [
    'tobimori.seo.lang' => 'en_US',
];
```

It's used for the `og:locale` meta tag, and can be applied to the `lang` attribute of your `<html>` tag using the `$site->lang()` site method.

### Schema.org usage

The plugin exposes the [`spatie/schema-org`](https://github.com/spatie/schema-org) package as site & page methods, with a global store you can access anywhere.

By default, the `$page`s `WebSite` schema will be pre-filled with data from fields. You can override the data with the methods listed below. This can also be configured via the `generateSchema` option.

#### `$page->schema($type)` / `$site->schema($type)`

Returns the existing Schema Object of the given type, or a new Schema Object if it hasn't been created yet for the current context.

#### `$page->schemas()` / `$site->schemas()`

Returns all schemas for a given context as array.

#### `schema($type)`

Generates a new Schema Object of the given type, without storing in the context.

#### Example

This example shows an FAQ page with multiple blocks, each containing a question and an answer.

```php
// site/snippets/blocks/faq.php
<?php $page->schema('FAQPage')->url('https://moeritz.io')->mainEntity(
  [
    ...($page->schema('FAQPage')->getProperty('mainEntity') ?? []),
    schema('Question')->name($block->question())->acceptedAnswer(
      schema('Answer')->text($block->title())
    )
  ]
);
```

```php
// At the end of `site/templates/default.php`
<?php snippet('seo/schemas') ?>
```

```json
// Output
{
  "@context": "https://schema.org",
  "@type": "FAQPage",
  "url": "https://moeritz.io",
  "mainEntity": [
    {
      "@type": "Question",
      "name": "How does it work?",
      "acceptedAnswer": { "@type": "Answer", "text": "It works like this." }
    },
    {
      "@type": "Question",
      "name": "Woah, it can handle multiple blocks?",
      "acceptedAnswer": { "@type": "Answer", "text": "Yes, it can." }
    }
  ]
}
```

## Options

| Option                    | Default                                                                                   | Description                                                                                                                            |
| ------------------------- | ----------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------- |
| `default.*`               | [see options.php](https://github.com/tobimori/kirby-seo/blob/main/config/options.php#L6)  | Sets several defaults for meta tags where necessary, see Meta Cascade                                                                  |
| `socialMedia.*`           | [see options.php](https://github.com/tobimori/kirby-seo/blob/main/config/options.php#L32) | Customize the social media accounts field for the site blueprint, disable them with `false` (Format: `'id' => 'placeholder'`)          |
| `robots.active`           | `true`                                                                                    | Whether the Robots module should be active (Robots.txt & Meta Tags generation)                                                         |
| `robots.followPageStatus` | `true`                                                                                    | Whether an unlisted page should be marked as non-indexable                                                                             |
| `robots.pageSettings`     | `true`                                                                                    | Whether to have the Robots settings as fields in the tab blueprints                                                                    |
| `robots.indicator`        | `true`                                                                                    | Show a page indexing state indicator next to the page status                                                                           |
| `robots.index`            | `fn () => !option('debug')`                                                               | Set a general indexing status for the site, can be a callable function, indexing is disallowed for pages with debug enabled by default |
| `robots.sitemap`          | `null`                                                                                    | A link to your sitemap, to be included in your `robots.txt`                                                                            |
| `robots.content`          | `[]`                                                                                      | A string or array of robots.txt rules, will fall back to a general `Allow`/`Disallow` depending on `robots.index`                      |
| `robots.types`            | `['index', 'follow', 'archive', 'imageindex', 'snippet']`                                 | Internally used option for available fields and Robots directive types, doesn't need to be changed                                     |
| `generateSchema`          | `true`                                                                                    | Whether to generate Schema.org JSON-LD with the default 'website' type                                                                 |
| `canonicalIncludesWWW`    | `false`                                                                                   | Whether to include the www. subdomain in the automatically generated canonical URL                                                     |
| `dateFormat`              | `null`                                                                                    | Date format for generation of page modified meta tags, will be set automatically by default based on your `data.handler`               |
| `lang`                    | `en_US`                                                                                   | Language code to be used in meta tags for single language setups                                                                       |
| `files.parent`            | `null`                                                                                    | Upload your OG images to a different page, e.g. `site.find('assets')`                                                                  |
| `files.template`          | `null`                                                                                    | Upload your OG images with a different template, e.g. `image`                                                                          |

Options allow you to fine tune the behaviour of the plugin. You can set them in your `config.php` file:

```php
return [
    'tobimori.seo' => [
        'generateSchema' => true,
        'canonicalIncludesWWW' => false,
        'dateFormat' => 'Y-m-d',
        'robots' => [
            'active' => true,
            'content' => [
                '*' => [
                    'Allow' => ['/'],
                    'Disallow' => ['/kirby', '/panel', '/content']
                ]
            ]
        ]
    ],
];
```

## Similar plugins

- [kirby-meta](https://github.com/fabianmichael/kirby-meta) by Fabian Michael

## License

[MIT License](./LICENSE)
Copyright © 2023 Tobias Möritz
