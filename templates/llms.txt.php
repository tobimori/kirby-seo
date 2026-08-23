<?php

/**
 * @var \Kirby\Cms\Pages $pages
 * @var \Kirby\Cms\Site $site
 * @var \tobimori\Seo\LlmContent $llmContent
 */

$clean = fn (string $value) => preg_replace('/\s+/', ' ', strip_tags($value));
$lines = [
	'# ' . $site->title()->value(),
	'',
];

if ($description = $site->description()->value()) {
	$lines[] = '> ' . $clean($description);
	$lines[] = '';
}

$lines[] = '## Pages';

foreach ($pages as $item) {
	$title = str_replace(['[', ']'], ['\\[', '\\]'], $item->title()->value());
	$line = '- [' . $title . '](' . $llmContent->markdownUrl($item) . ')';

	if ($description = $item->metadata()->metaDescription()->value()) {
		$line .= ': ' . $clean($description);
	}

	$lines[] = '';
	$lines[] = $line;
}

echo implode("\n", $lines) . "\n";
