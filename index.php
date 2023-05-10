<?php

@include_once __DIR__ . '/vendor/autoload.php';

use Kirby\Cms\App;
use Kirby\Cms\Page;
use Kirby\Filesystem\F;
use Kirby\Data\Yaml;
use Spatie\SchemaOrg\Schema;
use tobimori\Seo\Meta;
use tobimori\Seo\SchemaSingleton;

App::plugin('tobimori/seo', [
  'options' => [
    'default' => [
      'metaTitle' => fn (Page $page) => $page->title(),
      'metaTemplate' => '{{ title }} - {{ site.title }}',
      'ogTemplate' => '{{ title }}',
      'ogSiteName' => fn (Page $page) => $page->site()->title(),
      'ogType' => 'website',
      'ogLocale' => fn (Page $page) => $page->kirby()->language()->code() ?? 'en',
      'twitterCardType' => 'summary',
      'ogDescription' => fn (Page $page) => $page->metadata()->metaDescription(),
      'twitterCreator' => fn (Page $page) => $page->metadata()->twitterSite(),
    ],
    'socialMedia' => [
      'twitter' => 'https://twitter.com/my-company',
      'facebook' => 'https://facebook.com/my-company',
      'instagram' => 'https://instagram.com/my-company',
      'youtube' => 'https://youtube.com/channel/my-company',
      'linkedin' => 'https://linkedin.com/company/my-company',
    ],
    'generateSchema' => true,
    'canonicalIncludesWWW' => false,
    'dateFormat' => '%Y-%m-%d'
  ],
  'translations' => [
    'de' => Yaml::decode(F::read(__DIR__ . '/translations/de.yml')),
    'en' => Yaml::decode(F::read(__DIR__ . '/translations/en.yml'))
  ],
  'sections' => require __DIR__ . '/config/sections.php',
  'api' =>  require __DIR__ . '/config/api.php',
  'siteMethods' => [
    'schema' => fn ($type) => SchemaSingleton::getInstance($type),
    'schemas' => fn () => SchemaSingleton::getInstances(),
  ],
  'pageMethods' => [
    'schema' => fn ($type) => SchemaSingleton::getInstance($type, $this),
    'schemas' => fn () => SchemaSingleton::getInstances($this),
    'metadata' => fn (?string $lang = null) => new Meta($this, $lang),
  ],
  'snippets' => [
    'seo/schemas' => __DIR__ . '/snippets/schemas.php',
    'seo/head' => __DIR__ . '/snippets/head.php',
  ],
  'blueprints' => [
    'seo/site' => __DIR__ . '/blueprints/site.yml',
    'seo/page' => __DIR__ . '/blueprints/page.yml',
    'seo/fields/og-image' => __DIR__ . '/blueprints/fields/og-image.yml',
    'seo/fields/social-media' => require __DIR__ . '/blueprints/fields/social-media.php',
  ],
  'hooks' => [
    'page.render:before' => function (string $contentType, array $data, Page $page) {
      if (option('tobimori.seo.generateSchema')) {
        $page->schema('WebSite')->url($page->metadata()->canonicalUrl())->copyrightYear(date('Y'))->description($page->metadata()->metaDescription())->name($page->metadata()->metaTitle())->headline($page->metadata()->title());
      }
    }
  ],
]);

if (!function_exists('schema')) {
  function schema($type)
  {
    return Schema::{$type}();
  }
}
