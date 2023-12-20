<?php

/**
 * @var \Kirby\Cms\Page $page
 */

use Kirby\Cms\Html;

$meta = $page->metadata(); ?>

<title><?= $meta->title() ?></title>

<?php

$mapMeta = [
  'description' => $meta->metaDescription(),
  'date' => $page->modified($meta->dateFormat()),
  'twitter:card' => $meta->twitterCardType(),
  'twitter:title' => $meta->ogTitle(),
  'twitter:description' => $meta->ogDescription(),
  'twitter:image' => $ogImage?->url(),
  'twitter:site' => $meta->twitterSite(),
  'twitter:creator' => $meta->twitterCreator(),
];

foreach ($mapMeta as $name => $content) {
  if (is_a($content, 'Kirby\Content\Field') && $content->isEmpty()) continue;
  if (!$content) continue;

  echo Html::tag('meta', null, [
    'name' => $name,
    'content' => $content,
  ]) . PHP_EOL;
};

$mapLink = [
  'author' => $meta->metaAuthor(),
  'canonical' => $meta->canonicalUrl(),
];

foreach ($mapLink as $rel => $content) {
  if (is_a($content, 'Kirby\Content\Field') && $content->isEmpty()) continue;
  if (!$content) continue;

  echo Html::tag('link', null, [
    'rel' => $rel,
    'href' => $content,
  ]) . PHP_EOL;
};

$mapOg = [
  'og:title' => $meta->ogTitle(),
  'og:description' => $meta->ogDescription(),
  'og:url' => $meta->canonicalUrl(),
  'og:site_name' => $meta->ogSiteName(),
  'og:image' => $ogImage,
  'og:image:width' => $ogImage ? 1200 : null, // TODO: replace this with custom crop preset
  'og:image:height' => $ogImage ? 630 : null,
  'og:image:alt' => $meta->get('ogImage')->toFile()?->alt(),
  'og:type' => $meta->ogType(),
];

foreach ($mapOg as $property => $content) {
  if (is_a($content, 'Kirby\Content\Field') && $content->isEmpty()) continue;
  if (!$content) continue;

  echo Html::tag('meta', null, [
    'property' => $property,
    'content' => $content,
  ]) . PHP_EOL;
}; ?>

<?php /** Multi-lang */ ?>
<?php if ($kirby->languages()->count() > 1 && $kirby->language() !== null) : ?>
  <?php foreach ($kirby->languages() as $language) : ?>
    <link rel="alternate" hreflang="<?= strtolower(html($language->code())) ?>" href="<?= $page->url($language->code()) ?>">
    <?php if ($language !== $kirby->language()) : ?>
      <meta property="og:locale:alternate" content="<?= $language->locale(LC_ALL) ?>">
    <?php endif; ?>
  <?php endforeach; ?>

  <link rel="alternate" hreflang="x-default" href="<?= $page->url($kirby->defaultLanguage()->code()) ?>">
  <meta property="og:locale" content="<?= $kirby->language()->locale(LC_ALL) ?>">
<?php else : ?>
  <meta property="og:locale" content="<?= $meta->lang() ?>">
<?php endif ?>


<?php /** Robots */ ?>
<?php if ($meta->robots()) : ?>
  <meta name="robots" content="<?= $meta->robots() ?>">
<?php endif ?>
