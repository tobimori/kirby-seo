<?php

use Kirby\Cms\App;

/**
 * @var \Kirby\Cms\Page $page
 * @var \Kirby\Cms\Site $site
 * @var string|null $instructions
 * @var string|null $edit
 */

App::instance()->snippet(
	'seo/prompts/introduction',
	[
		'instructions' => $instructions ?? null,
		'edit' => $edit ?? null
	],
	return: false
); ?>

<task>
	Create a useful GLOBAL meta description for this site <site-title><?= $site->title()->toString() ?>.</site-title>
	This description is meant as FALLBACK for when the page does not have a meta description itself.
	This description should be unique and relevant to the site's content.

	The entire meta description SHOULD be between 120 and 158 characters long.

	You'll receive the content of the home page as well as any meta tags that are already set below.
</task>

<?php App::instance()->snippet('seo/prompts/site-meta', ['currentField' => 'metaDescription'], return: false);
App::instance()->snippet('seo/prompts/content', return: false);
