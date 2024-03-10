# Schema.org

The plugin exposes the [`spatie/schema-org`](https://github.com/spatie/schema-org) package as site & page methods, with a global store you can access anywhere.

By default, the `$page`s `WebSite` schema will be pre-filled with data from fields. You can override the data with the methods listed below. This can also be configured via the `tobimori.seo.generateSchema` option.

### Schema output

Place the snippet `seo/schemas` at the end of your template to output the JSON-LD snippet:

```php
<html>
	[...]
  <body>
    [...]
		// Before your </body> closing tag
    <?php snippet('seo/schemas'); ?>
  </body>
</html>
```

### Usage

#### `$page->schema($type)` / `$site->schema($type)`

Returns the existing Schema Object of the given type, or a new Schema Object if it hasn't been created yet for the current context.

#### `$page->schemas()` / `$site->schemas()`

Returns all schemas for a given context as array.

#### `schema($type)`

Generates a new Schema Object of the given type, without storing in the context.

### Example

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

## [Options](/config/options.php)

| Option           | Default | Accepts | Description                                    |
| ---------------- | ------- | ------- | ---------------------------------------------- |
| `generateSchema` | `true`  | Boolean | Enable/disable the automatic schema generation |

Options allow you to fine tune the behaviour of the plugin. You can set them in your `config.php` file:

```php
return [
    'tobimori.seo.generateSchema' => false,
];
```
