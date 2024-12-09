<?php

namespace tobimori\Seo;

use Kirby\Cms\Page;
use Spatie\SchemaOrg\Schema;

class SchemaSingleton
{
	private static $instances = [];

	private function __construct() {}

	public static function getInstance(string $type, Page|null $page = null): mixed
	{
		if (!class_exists('Spatie\SchemaOrg\Schema')) {
			return null;
		}

		if (!isset(self::$instances[$page?->id() ?? 'default'][$type])) {
			self::$instances[$page?->id() ?? 'default'][$type] = Schema::{$type}();
		}

		return self::$instances[$page?->id() ?? 'default'][$type];
	}

	public static function getInstances(Page|null $page = null): array
	{
		if (!class_exists('Spatie\SchemaOrg\Schema')) {
			return [];
		}

		return self::$instances[$page?->id() ?? 'default'] ?? [];
	}
}
