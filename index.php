<?php

@include_once __DIR__ . '/vendor/autoload.php';

use Kirby\Cms\App;
use Kirby\Filesystem\F;
use Kirby\Data\Yaml;
use Spatie\SchemaOrg\Schema;
use tobimori\Seo\SchemaSingleton;

App::plugin('tobimori/seo', [
  'translations' => [
    'de' => Yaml::decode(F::read(__DIR__ . '/translations/de.yml')),
    'en' => Yaml::decode(F::read(__DIR__ . '/translations/en.yml'))
  ],
  'sections' => [
    'heading-structure' => require_once __DIR__ . '/sections/heading-structure.php',
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
  ]
]);

if (!function_exists('schema')) {
  function schema($type)
  {
    return Schema::{$type}();
  }
}
