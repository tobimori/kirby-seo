<?php

/** @var \Kirby\Cms\Page|null $page */

$title = trim($page?->title()->value() ?? '') ?: 'Page not found';
$text = trim($page?->text()->value() ?? '') ?: 'The requested page could not be found.';
?>
# <?= $title ?>

<?= $text ?>
