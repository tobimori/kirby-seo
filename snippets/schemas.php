<?php

$siteSchema ??= true;
$pageSchema ??= true;

foreach (array_merge($siteSchema ? $site->schemas() : [], $pageSchema ? $page->schemas() : []) as $schema) {
	echo $schema;
}
