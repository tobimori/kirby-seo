<?php

use tobimori\Seo\SchemaSingleton;

return [
  'schema' => fn ($type) => SchemaSingleton::getInstance($type),
  'schemas' => fn () => SchemaSingleton::getInstances(),
  'lang' => fn () => option('tobimori.seo.default.lang')($this->homePage())
];
