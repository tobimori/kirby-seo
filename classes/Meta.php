<?php

namespace tobimori\Seo;

use Kirby\Content\Field;
use Kirby\Cms\Page;
use Kirby\Exception\InvalidArgumentException;
use Kirby\Toolkit\Str;
use Kirby\Toolkit\A;


/**
 * The Meta class is responsible for handling the meta data & cascading
 */
class Meta
{
  /** 
   * These values will be handled as 'field is empty' 
   */
  const DEFAULT_VALUES = ['[]', 'default'];

  protected Page $page;
  protected ?string $lang;
  protected array $meta = [];

  /**
   * Creates a new Meta instance
   */
  public function __construct(Page $page, ?string $lang = null)
  {
    $this->page = $page;
    $this->lang = $lang;

    if (method_exists($this->page, 'metaDefaults')) {
      $this->meta = $this->page->metaDefaults($this->lang);
    }
  }

  /**
   * Magic method to get a meta value by calling the method name
   */
  public function __call($name, $args = null): mixed
  {
    return $this->get($name);
  }

  /**
   * Get the meta value for a given key
   */
  public function get(string $key): Field
  {
    $cascade = option('tobimori.seo.cascade');
    if (count(array_intersect(get_class_methods($this), $cascade)) !== count($cascade)) {
      throw new InvalidArgumentException('Invalid cascade method in config. Please check your options for `tobimori.seo.cascade`.');
    }

    foreach ($cascade as $method) {
      if ($field = $this->$method($key)) {
        return $field;
      }
    }

    return new Field($this->page, $key, '');
  }

  /**
   * Get the meta value for a given key from the page's fields
   */
  protected function fields(string $key): Field|null
  {
    if (($field = $this->page->content($this->lang)->get($key))) {
      if (Str::contains($key, 'robots') && !option('tobimori.seo.robots.pageSettings')) return null;

      if ($field->isNotEmpty() && !A::has(self::DEFAULT_VALUES, $field->value())) {
        return $field;
      }
    }

    return null;
  }

  /**
   * Maps Open Graph fields to Meta fields for fallbackFields 
   * cascade method
   */
  const FALLBACK_MAP = [
    'ogTitle' => 'metaTitle',
    'ogDescription' => 'metaDescription',
    'ogTemplate' => 'metaTemplate',
  ];

  /**
   * We only allow the following cascade methods for fallbacks,
   * because we don't want to fallback to the config defaults for
   * Meta fields, because we most likely already have those set 
   * for the Open Graph fields
   */
  const FALLBACK_CASCADE = [
    'fields',
    'programmatic',
    'parent',
    'site'
  ];

  /**
   * Get the meta value for a given key using the fallback fields
   * defined above (usually Open Graph > Meta Fields)
   */
  protected function fallbackFields(string $key): Field|null
  {
    if (array_key_exists($key, self::FALLBACK_MAP)) {
      $fallback = self::FALLBACK_MAP[$key];
      $cascade = option('tobimori.seo.cascade');

      foreach (array_intersect($cascade, self::FALLBACK_CASCADE) as $method) {
        if ($field = $this->$method($fallback)) {
          return $field;
        }
      }
    }

    return null;
  }

  /**
   * Get the meta value for a given key from the page's meta
   * array, which can be set in the page's metaDefaults method
   */
  protected function programmatic(string $key): Field|null
  {
    if ($this->meta && array_key_exists($key, $this->meta)) {
      $val = $this->meta[$key];

      if (is_callable($val)) {
        $val = $val($this->page);
      }

      if (is_a($val, 'Kirby\Content\Field')) {
        return $val;
      }

      return new Field($this->page, $key, $val);
    }

    return null;
  }

  /**
   * Get the meta value for a given key from the page's parent,
   * if the page is allowed to inherit the value
   */
  protected function parent(string $key): Field|null
  {
    if ($this->canInherit($key)) {
      $parent = $this->page->parent();
      $parentMeta = new Meta($parent, $this->lang);
      if ($value = $parentMeta->get($key)) {
        return $value;
      }
    }

    return null;
  }

  /**
   * Get the meta value for a given key from the 
   * site's meta blueprint & content
   */
  protected function site(string $key): Field|null
  {
    if (($site = $this->page->site()->content($this->lang)->get($key)) && ($site->isNotEmpty() && !A::has(self::DEFAULT_VALUES, $site->value))) {
      return $site;
    }

    return null;
  }

  /**
   * Get the meta value for a given key from the 
   * config.php options
   */
  protected function options(string $key): Field|null
  {
    if ($option = option("tobimori.seo.default.{$key}")) {
      if (is_callable($option)) {
        $option = $option($this->page);
      }

      if (is_a($option, 'Kirby\Content\Field')) {
        return $option;
      }

      return new Field($this->page, $key, $option);
    }

    return null;
  }

  /**
   * Checks if the page can inherit a meta value from its parent
   */
  private function canInherit(string $key): bool
  {
    $parent = $this->page->parent();
    if (!$parent) return false;

    $inherit = $parent->metaInherit()->split();
    if (Str::contains($key, 'robots') && A::has($inherit, 'robots')) return true;
    return A::has($inherit, $key);
  }

  /**
   * Applies the title template, and returns the correct title 
   */
  public function title()
  {
    $title = $this->metaTitle();
    $template = $this->metaTemplate();

    $useTemplate = $this->page->useTitleTemplate();
    $useTemplate = $useTemplate->isEmpty() ? true : $useTemplate->toBool();

    $string = $title->value();
    if ($useTemplate) {
      $string = $this->page->toSafeString(
        $template,
        ['title' => $title]
      );
    }

    return new Field(
      $this->page,
      'metaTitle',
      $string
    );
  }

  /**
   * Applies the OG title template, and returns the OG Title 
   */
  public function ogTitle()
  {
    $title = $this->metaTitle();
    $template = $this->ogTemplate();

    $useTemplate = $this->page->useOgTemplate();
    $useTemplate = $useTemplate->isEmpty() ? true : $useTemplate->toBool();

    $string = $title->value();
    if ($useTemplate) {
      $string = $this->page->toSafeString(
        $template,
        ['title' => $title]
      );
    }

    return new Field(
      $this->page,
      'ogTitle',
      $string
    );
  }

  /**
   * Gets the canonical url for the page
   */
  public function canonicalUrl()
  {
    if (option('tobimori.seo.canonicalIncludesWWW') === false) {
      return preg_replace(array('/http:/', '/www\./'), array('https:', ''), $this->page->url());
    } else {
      return preg_replace('/http(s)?:\/\/(www.)?/', 'https://www.', $this->page->url());
    }
  }

  /**
   * Get the Twitter username from an account url set in the site options
   */
  public function twitterSite()
  {
    $accs = $this->page->site()->socialMediaAccounts()->toObject();
    $username = '';

    if ($accs->twitter()->isNotEmpty()) {
      // tries to match all twitter urls, and extract the username
      $matches = [];
      preg_match('/^(https?:\/\/)?(www\.)?twitter\.com\/(#!\/)?@?(?<name>[^\/\?]*)$/', $accs->twitter()->value(), $matches);
      if (isset($matches['name'])) $username = $matches['name'];
    }

    return new Field($this->page, 'twitter', $username);
  }

  /**
   * Gets the date format for modified meta tags, based on the registered date handler
   */
  public function dateFormat(): string
  {
    if ($custom = option('tobimori.seo.dateFormat')) {
      if (is_callable($custom)) {
        return $custom($this->page);
      }

      return $custom;
    }

    switch (option('date.handler')) {
      case 'strftime':
        return '%Y-%m-%d';
      case 'intl':
        return 'yyyy-MM-dd';
      case 'date':
      default:
        return 'Y-m-d';
    }
  }

  /**
   * Get the pages' robots rules as string
   */
  public function robots()
  {
    $robots = [];
    foreach (option('tobimori.seo.robots.types') as $type) {
      if (!$this->get('robots' . Str::ucfirst($type))->toBool()) {
        $robots[] = 'no' . Str::lower($type);
      }
    };

    if (A::count($robots) === 0) {
      $robots = ['all'];
    }

    return A::join($robots, ',');
  }

  /**
   * Get the og:image url
   */
  public function ogImage(): string|null
  {
    $field = $this->get('ogImage');

    if ($ogImage = $field->toFile()?->thumb([
      'width' => 1200,
      'height' => 630,
      'crop' => true,
    ])) {
      return $ogImage->url();
    }

    if ($field->isNotEmpty()) {
      return $field->value();
    }

    return null;
  }
}
