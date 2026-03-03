---
title: Optimizing Head Order
intro: Place high-priority elements before stylesheets and scripts
---

The order of elements in the `<head>` can affect perceived page performance. Ideally, the `<title>` element should appear early, before stylesheets and scripts, while other meta tags like Open Graph and description can go last. See [capo.js](https://rviscomi.github.io/capo.js/) for background on why this matters.

By default, `seo/head` outputs all tags in one block. If you want to split priority tags from the rest, use Kirby's [snippet slots](https://getkirby.com/docs/guide/templates/snippets#passing-data-to-snippets__slots):

```php
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php snippet('seo/head', slots: true) ?>
    <link rel="stylesheet" href="/assets/css/main.css">
    <script src="/assets/js/app.js" defer></script>
  <?php endsnippet() ?>
</head>
```

This outputs the `<title>` first, then your stylesheets and scripts from the slot, then the remaining meta tags (description, Open Graph, robots, etc.).
