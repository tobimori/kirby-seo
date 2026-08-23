<?php

/**
 * @var array $entries
 * @var \Kirby\Cms\Site $site
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

foreach ($entries as $entry) {
	$title = str_replace(['[', ']'], ['\\[', '\\]'], $entry['title']);
	$line = '- [' . $title . '](' . $entry['url'] . ')';

	if ($entry['description'] !== '') {
		$line .= ': ' . $clean($entry['description']);
	}

	$lines[] = '';
	$lines[] = $line;
}

echo implode("\n", $lines) . "\n";
