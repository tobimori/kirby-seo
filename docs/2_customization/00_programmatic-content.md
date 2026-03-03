---
title: Programmatic Content
intro: Set default SEO values from page models
---

Sometimes you want SEO fields to default to values from other fields, or generate them from code. A common example is using a plugin like [kirby-paparazzi](https://github.com/tobimori/kirby-paparazzi) to generate OG images for every page.

Add a `metaDefaults` method to a [page model](https://getkirby.com/docs/guide/templates/page-models). It returns an array of meta tag names mapped to their values. These defaults apply through the [Meta Cascade](0_getting-started/1_your-first-meta-tags) when no editor override exists.

```php
<?php
// site/models/article.php

use Kirby\Cms\Page;

class ArticlePage extends Page
{
  public function metaDefaults(string $lang = null): array
  {
    return [
      'og:image' => "{$this->url()}.png",
      'og:image:width' => 1230,
      'og:image:height' => 600,
      'description' => $this->content($lang)->summary()->value(),
    ];
  }
}
```

Kirby SEO picks the correct tag syntax from the name. Open Graph keys (starting with `og:`) get `property` and `content` attributes, link keys like `canonical` get `rel` and `href`, and everything else gets `name` and `content`.

## Custom tag attributes

If you need full control over a tag's output, pass an array with `tag` and `attributes`:

```php
return [
  // shorthand
  'description' => 'A page about something',

  // tag with inner content
  [
    'tag' => 'title',
    'content' => 'My Page Title',
  ],

  // tag with attributes
  [
    'tag' => 'meta',
    'attributes' => [
      'property' => 'og:image:alt',
      'content' => "An image of {$this->title()}",
    ],
  ],

  // link tag
  [
    'tag' => 'link',
    'attributes' => [
      'rel' => 'preconnect',
      'href' => 'https://fonts.googleapis.com',
    ],
  ],
];
```

## Global defaults via a plugin

Page models only apply to pages with a specific template. If you want to add meta tags to all pages, you can register a `metaDefaults` [page method](https://getkirby.com/docs/reference/plugins/extensions/page-methods) in a plugin:

```php
<?php
// site/plugins/my-meta/index.php

Kirby::plugin('my/meta', [
  'pageMethods' => [
    'metaDefaults' => function (string $lang = null): array {
      return [
        'og:image' => "{$this->url()}.png",
      ];
    },
  ],
]);
```
