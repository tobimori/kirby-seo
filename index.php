<?php

@include_once __DIR__ . '/vendor/autoload.php';

use Kirby\Cms\App;
use Kirby\Cms\Page;
use Kirby\Filesystem\F;
use Kirby\Data\Yaml;
use Spatie\SchemaOrg\Schema;
use tobimori\Seo\SchemaSingleton;

App::plugin('tobimori/seo', [
  'options' => [
    'generateSchema' => true,
  ],
  'translations' => [
    'de' => Yaml::decode(F::read(__DIR__ . '/translations/de.yml')),
    'en' => Yaml::decode(F::read(__DIR__ . '/translations/en.yml'))
  ],
  'sections' => [
    'heading-structure' => require_once __DIR__ . '/sections/heading-structure.php',
    'seo-preview' => require_once __DIR__ . '/sections/seo-preview.php',
  ],
  'siteMethods' => [
    'schema' => fn ($type) => SchemaSingleton::getInstance($type),
    'schemas' => fn () => SchemaSingleton::getInstances(),
  ],
  'pageMethods' => [
    'schema' => fn ($type) => SchemaSingleton::getInstance($type, $this),
    'schemas' => fn () => SchemaSingleton::getInstances($this),
  ],
  'snippets' => [
    'seo/schemas' => __DIR__ . '/snippets/schemas.php',
  ],
  'blueprints' => [
    'meta/site' => __DIR__ . '/blueprints/site.yml',
    'meta/page' => __DIR__ . '/blueprints/page.yml',
    'meta/og-image' => __DIR__ . '/blueprints/og-image.yml',
  ],
  'hooks' => [
    'page.render:before' => function (string $contentType, array $data, Page $page) {
      if (option('tobimori.seo.generateSchema')) {
        $page->schema('WebPage')->url($page->url());
      }
    }
  ]
]);

if (!function_exists('schema')) {
  function schema($type)
  {
    return Schema::{$type}();
  }
}
