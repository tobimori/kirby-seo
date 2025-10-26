<?php

use Kirby\Toolkit\Str;

/** @var \Kirby\Cms\Page $page
 ** @var \Kirby\Cms\Site $site
 ** @var string|null $instructions
 ** @var string|null $edit */

$meta = $page->metadata();

snippet('seo/prompts/introduction', [
	'instructions' => $instructions ?? null,
	'edit' => $edit ?? null
]); ?>

<task>
	Create a useful meta title for this page called <page-title><?= $page->title()->value() ?></page-title>. <?php if ($page->isHomePage()) : ?>This page is the homepage of the website. AVOID an overly generic title such as 'Home'.<?php endif ?>

	<?php if ($page->useTitleTemplate()->isEmpty() ? true : $page->useTitleTemplate()->toBool()):
		$template = $meta->get('metaTemplate');
		$templatePreview = $page->toString($template, ['title' => '{{ title }}']);
		$templateBaseLength = Str::length($page->toString($template, ['title' => '']));
		?>
		The final page title will be rendered as:

		<template><?= $templatePreview ?></template>

		Where {{ title }} is your page title. The entire title SHOULD be between <?= max(0, 50 - $templateBaseLength) ?>-<?= max(max(0, 50 - $templateBaseLength), 60 - $templateBaseLength) ?> characters long.
		DO NOT output the Title Template. ONLY output what should be placed inside {{ title }}. DO NOT repeat ANYTHING that exists in the template. You MUST NOT repeat the name of the site.
	<?php else: ?>
		Your response will be set as title without any changes. The entire title SHOULD be between 50-60 characters long.
	<?php endif; ?>

	If useful for the customers niche, include a keyword for the location. AVOID for global companies or niche subpages.
</task>

<?php snippet('seo/prompts/meta', ['currentField' => 'metaTitle']);
snippet('seo/prompts/content');
