<?php

/** @var \Kirby\Cms\Page $page
 ** @var \Kirby\Cms\Site $site */

$meta = $page->metadata();

snippet('seo/prompts/introduction'); ?>

<task>
	Create a useful meta description for this page called <page-title><?= $page->title()->value() ?></page-title>. <?php if ($page->isHomePage()) : ?>This page is the homepage of the website.<?php endif ?>

	The entire meta description SHOULD be between 120 and 158 characters long.

	You'll receive the content of the page as well as any meta tags that are already set below.
</task>

<?php snippet('seo/prompts/content');
