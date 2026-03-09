---
title: Schema.org (JSON-LD)
intro: Add structured data to your pages
---

Kirby SEO can output Schema.org structured data as JSON-LD. It uses the [spatie/schema-org](https://github.com/spatie/schema-org) package, which must be installed separately:

```bash
composer require spatie/schema-org
```

Once installed, a `WebSite` schema is generated automatically for every page with the page's title, description, and canonical URL. You can build on top of this or add your own schemas.

## Adding structured data

The plugin exposes a global store for Schema.org objects. You can access it from templates, snippets, or block snippets using `$page->schema()` and `$site->schema()`. Calling the same type twice returns the same instance, so you can build up a schema across different files.

```php
<?php
// site/templates/article.php

$page->schema('Article')
  ->headline($page->title()->value())
  ->datePublished($page->date()->toDate('c'))
  ->author(
    schema('Person')
      ->name($page->author()->value())
  );
```

`$page->schema($type)` returns the stored schema for that type, or creates a new one if it doesn't exist yet. Both also exist as `$site->schema()` and `$site->schemas()` for site-level schemas.

The global `schema($type)` function creates a new instance without storing it. Use it for nested objects like the `Person` above that don't need their own top-level entry.

## Building schemas across blocks

Because `$page->schema()` always returns the same instance, you can add to a schema from individual block snippets. This is useful for types like `FAQPage` where the content comes from multiple blocks:

```php
<?php
// site/snippets/blocks/faq.php

$page->schema('FAQPage')
  ->mainEntity([
    ...($page->schema('FAQPage')->getProperty('mainEntity') ?? []),
    schema('Question')
      ->name($block->question())
      ->acceptedAnswer(
        schema('Answer')->text($block->answer())
      ),
  ]);
```

Each block appends its question to the `mainEntity` array. The final output combines all of them:

```json
{
	"@context": "https://schema.org",
	"@type": "FAQPage",
	"mainEntity": [
		{
			"@type": "Question",
			"name": "How does it work?",
			"acceptedAnswer": {
				"@type": "Answer",
				"text": "It works like this."
			}
		},
		{
			"@type": "Question",
			"name": "Can it handle multiple blocks?",
			"acceptedAnswer": {
				"@type": "Answer",
				"text": "Yes, it can."
			}
		}
	]
}
```

## Disabling the default schema

If you don't want the automatic `WebSite` schema, disable it in your config:

```php
'tobimori.seo' => [
  'generateSchema' => false,
],
```
