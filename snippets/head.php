<?php

/**
 * @var \Kirby\Cms\Page $page
 */

use Kirby\Cms\Html;

$meta = $page->metadata(); ?>

<title><?= $meta->title() ?></title>

<?php

$ogImage = $meta->ogImage()->toFile()?->thumb([
  'width' => 1200,
  'height' => 630,
  'crop' => true,
]);

$map = [
  'description' => $meta->metaDescription(),
  'author' => $meta->metaAuthor(),
  'date' => $page->modified(option('tobimori.seo.dateFormat')),
  'canonical' => $meta->canonicalUrl(),
  'twitter:card' => $meta->twitterCardType(),
  'twitter:title' => $meta->ogTitle(),
  'twitter:description' => $meta->ogDescription(),
  'twitter:image' => $ogImage?->url(),
  'twitter:site' => $meta->twitterSite(),
  'twitter:creator' => $meta->twitterCreator(),
];

foreach ($map as $name => $content) {
  if (is_a($content, 'Kirby\Cms\Field') && $content->isEmpty()) continue;
  if (!$content) continue;

  echo Html::tag('meta', null, [
    'name' => $name,
    'content' => $content,
  ]) . PHP_EOL;
};

$mapOg = [
  'og:title' => $meta->ogTitle(),
  'og:description' => $meta->ogDescription(),
  'og:url' => $meta->canonicalUrl(),
  'og:site_name' => $meta->ogSiteName(),
  'og:image' => $ogImage?->url(),
  'og:image:width' => $ogImage?->width(),
  'og:image:height' => $ogImage?->height(),
  'og:image:alt' => $ogImage?->alt(),
  'og:type' => $meta->ogType(),
];

foreach ($mapOg as $property => $content) {
  if (is_a($content, 'Kirby\Cms\Field') && $content->isEmpty()) continue;
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
<?php if ($page->status() == 'unlisted') : ?>
  <meta name="robots" content="none">
<?php endif ?>