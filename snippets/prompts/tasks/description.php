<?php

use Kirby\Cms\App;

/**
 * @var \Kirby\Cms\Page $page
 * @var \Kirby\Cms\Site $site
 * @var string|null $instructions
 * @var string|null $edit
 */

$meta = $page->metadata();

App::instance()->snippet(
	'seo/prompts/introduction',
	[
		'instructions' => $instructions ?? null,
		'edit' => $edit ?? null
	],
	return: false
); ?>

<task>
	Create a useful meta description for this page called <page-title><?= $page->title()->toString() ?></page-title>. <?php if ($page->isHomePage()): ?>This page is the homepage of the website.<?php endif ?>

	The entire meta description SHOULD be between 120 and 158 characters long.

	You'll receive the content of the page as well as any meta tags that are already set below.
</task>

<?php App::instance()->snippet('seo/prompts/meta', ['currentField' => 'metaDescription'], return: false);
App::instance()->snippet('seo/prompts/content', return: false);
