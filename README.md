# Kirby SEO

SEO for Kirby CMS – done right.

## Requirements

- Kirby 3.9+
- PHP 8.1+

## The Meta Cascade

Kirby SEO is built with a cascading approach in mind. This means that you can define meta tags on multiple levels, and they will be merged together based on their priority:

1. **Page fields**: The default page blueprint that allows you to specify most options
2. **Programmatic content**: With Page models you can programmatically set meta tags like you want
3. **Inherited fields from parent page**: Most fields can be configured to inherit to child pages
4. **Site globals**: The site blueprint allows you to set global defaults for all pages
5. **Options**: The last fallback is defined in the plugin options

If any setting is left empty, it will fallback to the next level. In this way, the plugin provides _cascades_ to form the final meta data.

## Usage

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
<?php snippet('schemas') ?>
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

## Roadmap

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
