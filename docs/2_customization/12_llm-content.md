---
title: Customizing LLM-friendly Content
intro: Convert HTML pages and customize llms.txt
---

Explicit `.md.php` templates give you full control over Markdown. Automatic conversion is useful when the existing HTML templates already contain well-defined main content. The following setup adds Markdown to those pages without a second set of templates.

## Convert existing pages

First, install the default HTML-to-Markdown converter:

```sh
composer require league/html-to-markdown
```

Mark the relevant content in your HTML template. Keep navigation, forms, and other interface elements outside this container:

```php
<main data-agentic-content>
  <h1><?= $page->title() ?></h1>
  <?= $page->text()->kirbytext() ?>
</main>
```

Then enable conversion in `site/config/config.php`:

```php
<?php

return [
  'tobimori.seo' => [
    'agentic' => [
      'markdown' => [
        'auto' => true,
      ],
    ],
  ],
];
```

Open a page with `.md` added to its URL to check the result. For example, `/about` becomes `/about.md`.

Kirby SEO still uses an explicit `.md.php` representation when one exists. Conversion handles only pages without one.

## Use different content selectors

The default selectors are `[data-agentic-content]`, `main`, and `article`. Kirby SEO uses the first matching element. It never converts the complete `<body>`.

Set your own ordered list if the templates use different markup:

```php
'markdown' => [
  'auto' => true,
  'selectors' => ['#content', '.article-content', 'main'],
],
```

Selectors can be element names, IDs, classes, attribute selectors, or XPath expressions.

## Replace the converter

You do not need `league/html-to-markdown` when you provide a converter. Set `agentic.markdown.converter` to a callable:

```php
'markdown' => [
  'auto' => true,
  'converter' => fn (string $html): string => myMarkdownConverter($html),
],
```

The callable receives the extracted HTML. The [options reference](3_reference/0_options) lists the other accepted converter forms.

## Choose pages for llms.txt

By default, `llms.txt` includes every published, indexable page with Markdown available. Use `agentic.llmsTxt.pages` to filter or reorder the page collection:

```php
use Kirby\Cms\Pages;

'llmsTxt' => [
  'pages' => fn (Pages $pages): Pages => $pages->listed(),
],
```

The normal publication, translation, robots, and Markdown checks still apply after this callback.

## Replace the llms.txt template

Create `site/templates/llms.txt.php` to replace the default output. The template receives `$site`, `$pages`, and `$entries`. Each entry contains its page, title, description, and Markdown URL:

```php
<?php

echo '# ' . $site->title() . "\n\n## Documentation\n";

foreach ($entries as $entry) {
  echo "\n- [{$entry['title']}]({$entry['url']})\n";
}
```

## Static page caches

Content negotiation needs the request to reach Kirby. A static page cache can return cached HTML before Kirby reads the `Accept` header. Use the direct `.md` URLs for these clients, or exclude the affected pages from the static cache when negotiation on the normal URL is required.
