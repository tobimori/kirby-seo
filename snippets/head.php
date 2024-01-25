<?php

/**
 * @var \Kirby\Cms\Page $page
 */

use Kirby\Cms\Html;

$tags = $page->metadata()->snippetData();

foreach ($tags as $tag) {
	echo Html::tag($tag['tag'], $tag['content'] ?? null, $tag['attributes'] ?? []) . PHP_EOL;
}
