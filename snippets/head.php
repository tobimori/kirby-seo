<?php

/**
 * @var \Kirby\Cms\Page $page
 * @var \Kirby\Template\Slot|null $slot
 */

use Kirby\Cms\Html;

/** @var list<array{tag: string, content?: string|null, attributes?: array<string, mixed>|null, priority?: bool}> $tags */
$tags = $page->metadata()->snippetData();

// if we're using slots, the user wants to output priority tags such as <title>
// before their stylesheet, script, etc. tags
if (isset($slot)) {
	foreach (array_filter($tags, static fn (array $tag): bool => ($tag['priority'] ?? false) === true) as $tag) {
		echo Html::tag($tag['tag'], $tag['content'] ?? null, $tag['attributes'] ?? []) . PHP_EOL;
	}

	echo $slot->render();

	$tags = array_filter($tags, static fn (array $tag): bool => ($tag['priority'] ?? false) !== true);
}

// then output other tags as normal
// this is unfiltered if slots is not set.
foreach ($tags as $tag) {
	echo Html::tag($tag['tag'], $tag['content'] ?? null, $tag['attributes'] ?? []) . PHP_EOL;
}
