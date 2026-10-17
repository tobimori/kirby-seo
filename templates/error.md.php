<?php

/** @var \Kirby\Cms\Page|null $page */

$title = trim($page?->title()->toString() ?? '') ?: 'Page not found';
$text = trim($page?->text()->toString() ?? '') ?: 'The requested page could not be found.';
?>
# <?= $title ?>

<?= $text ?>
