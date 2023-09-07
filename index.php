<?php

@include_once __DIR__ . '/vendor/autoload.php';

use Kirby\Cms\App;
use Kirby\Cms\Page;
use Kirby\Filesystem\F;
use Kirby\Toolkit\A;
use Kirby\Data\Yaml;
use Kirby\Filesystem\Dir;
use Kirby\Http\Response;
use Spatie\SchemaOrg\Schema;
use tobimori\Seo\Meta;
use tobimori\Seo\SchemaSingleton;

// shamelessly borrowed from distantnative/retour-for-kirby
if (
  version_compare(App::version() ?? '0.0.0', '4.0.0-beta.1', '<') === true ||
  version_compare(App::version() ?? '0.0.0', '5.0.0', '>') === true
) {
  throw new Exception('Kirby SEO requires Kirby 4');
}

App::plugin('tobimori/seo', [
  'options' => [
    'default' => [ // default field values for metadata, format is [field => value]
      'metaTitle' => fn (Page $page) => $page->title(),
      'metaTemplate' => '{{ title }} - {{ site.title }}',
      'ogTemplate' => '{{ title }}',
      'ogSiteName' => fn (Page $page) => $page->site()->title(),
      'ogType' => 'website',
      'twitterCardType' => 'summary',
      'ogDescription' => fn (Page $page) => $page->metadata()->metaDescription(),
      'twitterCreator' => fn (Page $page) => $page->metadata()->twitterSite(),
      'lang' => fn (Page $page) => $page->kirby()->language()?->locale(LC_ALL) ?? $page->kirby()->option('tobimori.seo.lang', 'en_US'),
      // default for robots: noIndex if global index configuration is set, otherwise fall back to page status
      'robotsIndex' => function (Page $page) {
        $index = $page->kirby()->option('tobimori.seo.robots.index');
        if (is_callable($index)) {
          $index = $index();
        };

        if (!$index) return false;

        return $page->kirby()->option('tobimori.seo.robots.followPageStatus', true) ? $page->isListed() : true;
      },
      'robotsFollow' => fn (Page $page) => $page->kirby()->option('tobimori.seo.default.robotsIndex')($page),
      'robotsArchive' => fn (Page $page) => $page->kirby()->option('tobimori.seo.default.robotsIndex')($page),
      'robotsImageindex' => fn (Page $page) => $page->kirby()->option('tobimori.seo.default.robotsIndex')($page),
      'robotsSnippet' => fn (Page $page) => $page->kirby()->option('tobimori.seo.default.robotsIndex')($page),
    ],
    'socialMedia' => [ // default fields for social media links, format is [field => placeholder]
      'twitter' => 'https://twitter.com/my-company',
      'facebook' => 'https://facebook.com/my-company',
      'instagram' => 'https://instagram.com/my-company',
      'youtube' => 'https://youtube.com/channel/my-company',
      'linkedin' => 'https://linkedin.com/company/my-company',
    ],
    'robots' => [
      'active' => true, // whether robots handling should be done by the plugin
      'followPageStatus' => true, // should unlisted pages be noindex by default?
      'pageSettings' => true, // whether to have robots settings on each page
      'indicator' => true, // whether the indicator should be shown in the panel
      'index' => fn () => !option('debug'), // default site-wide robots setting
      'sitemap' => null, // sets sitemap url, will be replaced by plugin sitemap in the future
      'content' => [], // custom robots content
      'types' => ['index', 'follow', 'archive', 'imageindex', 'snippet'] // available robots types
    ],
    'generateSchema' => true, // whether to generate default schema.org data
    'canonicalIncludesWWW' => false, // whether to include www. in canonical URLs
    'lang' => 'en_US', // default language, used for single-language sites
    'dateFormat' => null, // custom date format,
    'files' => [
      'parent' => null,
      'template' => null,
    ]
  ],
  // load all commands automatically
  'commands' => A::keyBy(A::map(
    Dir::read(__DIR__ . '/config/commands'),
    fn ($file) => A::merge([
      'id' => 'seo:' . F::name($file),
    ], require __DIR__ . '/config/commands/' . $file)
  ), 'id'),
  // get all files from /translations and register them as language files
  'translations' => A::keyBy(A::map(
    Dir::read(__DIR__ . '/translations'),
    fn ($file) => A::merge([
      'lang' => F::name($file),
    ], Yaml::decode(F::read(__DIR__ . '/translations/' . $file)))
  ), 'lang'),
  'sections' => require __DIR__ . '/config/sections.php',
  'api' =>  require __DIR__ . '/config/api.php',
  'siteMethods' => [
    'schema' => fn ($type) => SchemaSingleton::getInstance($type),
    'schemas' => fn () => SchemaSingleton::getInstances(),
    'lang' => fn () => option('tobimori.seo.default.lang')($this->homePage())
  ],
  'pageMethods' => [
    'schema' => fn ($type) => SchemaSingleton::getInstance($type, $this),
    'schemas' => fn () => SchemaSingleton::getInstances($this),
    'metadata' => fn (?string $lang = null) => new Meta($this, $lang),
    'robots' => fn (?string $lang = null) => $this->metadata($lang)->robots(),
  ],
  'snippets' => [
    'seo/schemas' => __DIR__ . '/snippets/schemas.php',
    'seo/head' => __DIR__ . '/snippets/head.php',
    'seo/robots.txt' => __DIR__ . '/snippets/robots.txt.php',
  ],
  'blueprints' => [
    'seo/site' => __DIR__ . '/blueprints/site.yml',
    'seo/page' => __DIR__ . '/blueprints/page.yml',
    'seo/fields/og-image' => require __DIR__ . '/blueprints/fields/og-image.php',
    'seo/fields/og-group' => __DIR__ . '/blueprints/fields/og-group.yml',
    'seo/fields/meta-group' => __DIR__ . '/blueprints/fields/meta-group.yml',
    'seo/fields/robots' => require __DIR__ . '/blueprints/fields/robots.php',
    'seo/fields/site-robots' => require __DIR__ . '/blueprints/fields/site-robots.php',
    'seo/fields/social-media' => require __DIR__ . '/blueprints/fields/social-media.php',
  ],
  'hooks' => [
    'page.render:before' => function (string $contentType, array $data, Page $page) {
      if (option('tobimori.seo.generateSchema')) {
        $page->schema('WebSite')->url($page->metadata()->canonicalUrl())->copyrightYear(date('Y'))->description($page->metadata()->metaDescription())->name($page->metadata()->metaTitle())->headline($page->metadata()->title());
      }
    }
  ],
  'routes' => [
    [
      'pattern' => 'robots.txt',
      'action' => function () {
        if (option('tobimori.seo.robots.active', true)) {
          $content = snippet('seo/robots.txt', [], true);
          return new Response($content, 'text/plain', 200);
        }

        $this->next();
      }
    ]
  ],
]);

if (!function_exists('schema')) {
  function schema($type)
  {
    return Schema::{$type}();
  }
}
