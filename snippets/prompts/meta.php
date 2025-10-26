<?php

/** @var \Kirby\Cms\Page $page
 ** @var \Kirby\Cms\Site $site
 ** @var string|null $currentField */

$meta = $page->metadata();
$currentField = $currentField ?? null;

$metaFields = [
	'metaTitle' => 'Meta Title',
	'metaDescription' => 'Meta Description',
	'ogTitle' => 'Open Graph Title',
	'ogDescription' => 'Open Graph Description'
];
?>

<existing-metadata>
<?php foreach ($metaFields as $key => $label) : ?>
<?php
	$value = $meta->get($key);
	if ($currentField === $key || !$value || $value === '') {
		continue;
	}
	?>
	<<?= $key ?>><?= $value ?></<?= $key ?>>
<?php endforeach ?>
</existing-metadata>
