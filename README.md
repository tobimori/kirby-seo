![Kirby SEO Banner](/.github/banner.png)

<h1 align="center">Kirby SEO</h1>
<p align="center">SEO for Kirby CMS – done right.</p>

---

## Features

- All-in-one SEO and meta solution
- The Meta Cascade: Intelligently merge meta data from multiple sources
- Schema.org support with fluent classes
- _to be extended_

## Requirements

- Kirby 3.9+
- PHP 8.1+

## How it works - The Meta Cascade

Kirby SEO is built with a cascading approach in mind. This means that you can define meta tags on multiple levels, and they will be merged together based on their priority:

1. **Page fields**: The default page blueprint that allows you to specify most options
2. **Programmatic content**: With Page models you can programmatically set meta tags like you want
3. **Inherited fields from parent page**: Most fields can be configured to inherit to child pages
4. **Site globals**: The site blueprint allows you to set global defaults for all pages
5. **Options**: The last fallback is defined in the plugin options

If any setting is left empty, it will fallback to the next level. In this way, the plugin provides _cascades_ to form the final meta data.

## Usage

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
<head>
    <?php snippet('seo/head'); ?>
</head>
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

By default, the `$page`s `WebSite` schema will be pre-filled with data from fields. You can override the data with the methods listed below.

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

| Option                 | Default                                                                    | Description                                                                                        |
| ---------------------- | -------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------- |
| `default.*`            | [see index.php](https://github.com/tobimori/kirby-seo/blob/main/index.php) | Sets several defaults for meta tags where necessary, see Meta Cascade                              |
| `socialMedia.*`        | [see index.php](https://github.com/tobimori/kirby-seo/blob/main/index.php) | Customize the social media accounts field for the site blueprint (Format: `'id' => 'placeholder'`) |
| `generateSchema`       | `true`                                                                     | Whether to generate Schema.org JSON-LD with the default 'website' type                             |
| `canonicalIncludesWWW` | `false`                                                                    | Whether to include the www. subdomain in the automatically generated canonical URL                 |
| `dateFormat`           | `%Y-%m-%d`                                                                 | Date format for generation of page modified meta tags                                              |
| `lang`                 | `en_US`                                                                    | Language code to be used in meta tags for single language setups                                   |

Options allow you to fine tune the behaviour of the plugin. You can set them in your `config.php` file:

```php
return [
    'tobimori.seo' => [
        'generateSchema' => true,
        'canonicalIncludesWWW' => false,
        'dateFormat' => '%Y-%m-%d'
    ],
];
```

## Roadmap

- [ ] Robots page status as section
- [ ] Google pixel length calculation for preview
- [ ] `seo/inheritables` page blueprint, containing only inheritable fields for use with pages that don't have a frontend representation
- [ ] Toolkit for programmatic image generation with Puppeteer/BrowserShot (might be separate plugin)
- [ ] Social Media Links handling in sameAs schema.org property, and `og:see_also` meta tag
- [ ] Keywords/Tags field
- [ ] `robots.txt` generation
- [ ] `sitemap.xml` generation
- [ ] Favicon and webmanifest generation
- [ ] SEO Overview page/dashboard
- [ ] Site Verification fields
- [ ] Robots field section, for per-page overrides

This roadmap is a courtesy and subject to change at any time.
New features will be added as soon as I need them for my own projects.

## Similiar plugins

- [kirby-meta](https://github.com/fabianmichael/kirby-meta) by Fabian Michael
- [kirby-meta-knight](https://github.com/diesdasdigital/kirby-meta-knight) by diesdas.digital (unmaintained, but have a look at forks!)

## License

[MIT License](./LICENSE)
Copyright © 2023 Tobias Möritz
