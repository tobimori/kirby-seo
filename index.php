<?php

@include_once __DIR__ . '/vendor/autoload.php';

use Kirby\Cms\App;
use Kirby\Filesystem\F;
use Kirby\Data\Yaml;

App::plugin('tobimori/seo', [
  'translations' => [
    'de' => Yaml::decode(F::read(__DIR__ . '/translations/de.yml')),
    'en' => Yaml::decode(F::read(__DIR__ . '/translations/en.yml'))
  ],
  'sections' => [
    'heading-structure' => require_once __DIR__ . '/sections/heading-structure.php',
  ]
]);
