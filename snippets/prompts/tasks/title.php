<?php

/** @var \Kirby\Cms\Page $page
 ** @var \Kirby\Cms\Site $site */

$meta = $page->metadata(); ?>

<task>
Create a useful meta title for this page called <page-title><?= $page->title()->value() ?></page-title>. <?php if ($page->isHomePage()) : ?>This page is the homepage of the website. AVOID an overly generic title such as 'Home'.<?php endif ?>

<?php if ($page->useTitleTemplate()->isEmpty() ? true : $page->useTitleTemplate()->toBool()): ?>
The final page title will be rendered as:

<template><?= $page->toString($meta->get('metaTemplate'), ['title' => '{{ title }}']) ?></template>

Where {{ title }} is your page title. This template is xxx characters long. Your output title SHOULD BE between xxx-xxx characters long, so that the entire title is between 50-60 characters long.
DO NOT output the Title Template. ONLY output what should be placed inside {{ title }}. DO NOT repeat ANYTHING that exists in the template. You MUST NOT repeat the name of the site.
<?php else: ?>
Your response will be set as title without any changes. The entire title SHOULD be between 50-60 characters long.
<?php endif; ?>

If useful for the customers niche, include a keyword for the location. AVOID for global companies or niche subpages.
</task>

<?php snippet('seo/prompts/content'); ?>