<?php

if (!class_exists('Spatie\SchemaOrg\Schema')) {
	return;
}

$siteSchema ??= true;
$pageSchema ??= true;

foreach (array_merge($siteSchema ? $site->schemas() : [], $pageSchema ? $page->schemas() : []) as $schema) {
	echo $schema;
}
