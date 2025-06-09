<?php

use Kirby\Cms\Language;
use tobimori\Seo\Meta;
use tobimori\Seo\SchemaSingleton;

return [
	'schema' => fn ($type) => SchemaSingleton::getInstance($type, $this),
	'schemas' => fn () => SchemaSingleton::getInstances($this),
	'metadata' => fn (?Language $lang = null) => new Meta($this, $lang),
	'robots' => fn (?Language $lang = null) => $this->metadata($lang)->robots(),
];
