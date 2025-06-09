<?php

/**
 * @var \Kirby\Cms\Page $page
 */

use Kirby\Cms\Html;

$tags = $page->metadata()->snippetData();

// if we're using slots, the user wants to output priority tags such as <title>
// before their stylesheet, script, etc. tags
if (isset($slot)) {
	foreach (array_filter($tags, fn($tag) => $tag['priority']) as $tag) {
		echo Html::tag($tag['tag'], $tag['content'] ?? null, $tag['attributes'] ?? []) . PHP_EOL;
	}

	echo $slot;

	$tags = array_filter($tags, fn($tag) => !$tag['priority']);
}

// then output other tags as normal
// this is unfiltered if slots is not set.
foreach ($tags as $tag) {
	echo Html::tag($tag['tag'], $tag['content'] ?? null, $tag['attributes'] ?? []) . PHP_EOL;
}
