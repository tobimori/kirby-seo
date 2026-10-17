<?php

/**
 * @var \Kirby\Cms\Page $page
 * @var \Kirby\Template\Slot|null $slot
 */

use Kirby\Cms\Html;

$tags = $page->metadata()->snippetData();

$render = static function (array $tag): string {
	/** @var array{tag: string, attributes?: array<string, mixed>|null, content?: string|Stringable|array<array-key, mixed>|null, priority?: bool} $tag */
	// field values are `Stringable`, `Html::tag()` expects a string
	$content = $tag['content'] ?? null;
	return Html::tag($tag['tag'], $content instanceof Stringable ? (string)$content : $content, $tag['attributes'] ?? []) . PHP_EOL;
};

// if we're using slots, the user wants to output priority tags such as <title>
// before their stylesheet, script, etc. tags
if (isset($slot)) {
	foreach (array_filter($tags, static fn (array $tag): bool => ($tag['priority'] ?? false) === true) as $tag) {
		echo $render($tag);
	}

	echo $slot->render();

	$tags = array_filter($tags, static fn (array $tag): bool => ($tag['priority'] ?? false) !== true);
}

// then output other tags as normal
// this is unfiltered if slots is not set.
foreach ($tags as $tag) {
	echo $render($tag);
}
