<?php

/**
 * @var list<array{title: string, url: string, description: string}> $entries
 * @var \Kirby\Cms\Site $site
 */

$clean = fn (string $value): string => preg_replace('/\s+/', ' ', strip_tags($value)) ?? '';
$lines = [
	'# ' . $site->title()->toString(),
	''
];

if ($description = $site->metaDescription()->toString()) {
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
