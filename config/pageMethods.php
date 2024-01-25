<?php

use tobimori\Seo\Meta;
use tobimori\Seo\SchemaSingleton;

return [
	'schema' => fn ($type) => SchemaSingleton::getInstance($type, $this),
	'schemas' => fn () => SchemaSingleton::getInstances($this),
	'metadata' => fn (?string $lang = null) => new Meta($this, $lang),
	'robots' => fn (?string $lang = null) => $this->metadata($lang)->robots(),
];
