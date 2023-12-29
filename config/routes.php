<?php

use Kirby\Cms\Page;
use Kirby\Http\Response;
use tobimori\Seo\Sitemap\SitemapIndex;

return [
  [
    'pattern' => 'robots.txt',
    'action' => function () {
      if (option('tobimori.seo.robots.active', true)) {
        $content = snippet('seo/robots.txt', [], true);
        return new Response($content, 'text/plain', 200);
      }

      $this->next();
    }
  ],
  [
    'pattern' => 'sitemap.xsl',
    'action' => function () {
      if (!option('tobimori.seo.sitemap.active', true)) {
        $this->next();
      }

      kirby()->response()->type('text/xsl');
      kirby()->setCurrentTranslation(option('tobimori.seo.sitemap.lang', 'en'));

      return Page::factory([
        'slug' => 'sitemap',
        'template' => 'sitemap',
        'model' => 'sitemap',
        'content' => [
          'title' => 'Sitemap',
        ],
      ])->render(contentType: 'xsl');
    }
  ],
  [
    'pattern' => 'sitemap.xml',
    'action' => function () {
      if (!option('tobimori.seo.sitemap.active', true)) {
        $this->next();
      }

      SitemapIndex::instance()->generate();
      kirby()->response()->type('text/xml');
      return Page::factory([
        'slug' => 'sitemap',
        'template' => 'sitemap',
        'model' => 'sitemap',
        'content' => [
          'title' => 'Sitemap',
          'index' => null,
        ],
      ])->render(contentType: 'xml');
    }
  ],
  [
    'pattern' => 'sitemap-(:any).xml',
    'action' => function (string $index) {
      if (!option('tobimori.seo.sitemap.active', true)) {
        $this->next();
      }

      SitemapIndex::instance()->generate();
      if (!SitemapIndex::instance()->isValidIndex($index)) {
        $this->next();
      }

      kirby()->response()->type('text/xml');
      return Page::factory([
        'slug' => 'sitemap',
        'template' => 'sitemap',
        'model' => 'sitemap',
        'content' => [
          'title' => 'Sitemap',
          'index' => $index,
        ],
      ])->render(contentType: 'xml');
    }
  ]
];
