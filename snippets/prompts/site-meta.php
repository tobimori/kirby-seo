<?php

/** @var \Kirby\Cms\Site $site
 ** @var string|null $currentField */

$currentField = $currentField ?? null;

$metaFields = [
	'metaDescription' => 'Site Meta Description',
	'ogDescription' => 'Site Open Graph Description',
	'ogSiteName' => 'Site Name'
];
?>

<existing-site-metadata>
<?php foreach ($metaFields as $key => $label) : ?>
<?php
	$value = $site->$key()->value();
	if ($currentField === $key || !$value || $value === '') {
		continue;
	}
	?>
	<<?= $key ?>><?= $value ?></<?= $key ?>>
<?php endforeach ?>
</existing-site-metadata>
