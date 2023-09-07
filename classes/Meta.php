<?php

namespace tobimori\Seo;

use Kirby\Content\Field;
use Kirby\Cms\Page;
use Kirby\Toolkit\Str;
use Kirby\Toolkit\A;


class Meta
{
  const DEFAULT_VALUES = ['[]', 'default'];

  protected Page $page;
  protected ?string $lang;
  protected array $meta = [];

  public function __call($name, $args = null): mixed
  {
    return $this->get($name);
  }

  public function __construct(Page $page, ?string $lang = null)
  {
    $this->page = $page;
    $this->lang = $lang;

    if (method_exists($this->page, 'metaDefaults')) {
      $this->meta = $this->page->metaDefaults($this->lang);
    }
  }

  public function get(string $key): Field
  {
    if (($field = $this->page->content($this->lang)->get($key))) { // Page field
      if (Str::contains($key, 'robots') && !option('tobimori.seo.robots.pageSettings')) return $this->getFallback($key);

      if ($field->isNotEmpty() && !A::has(self::DEFAULT_VALUES, $field->value())) {
        return $field;
      }
    }

    return $this->getFallback($key);
  }

  public function getFallback(string $key): Field
  {
    if ($this->meta && array_key_exists($key, $this->meta)) { // Programmatic content
      $val = $this->meta[$key];

      if (is_callable($val)) {
        $val = $val($this->page);
      }

      if (is_a($val, 'Kirby\Content\Field')) {
        return $val;
      }

      return new Field($this->page, $key, $val);
    }

    if ($this->canInherit($key)) { // Inheritance from parent
      $parent = $this->page->parent();
      $parentMeta = new Meta($parent, $this->lang);
      if ($value = $parentMeta->get($key)) {
        return $value;
      }
    }

    if (($site = $this->page->site()->content($this->lang)->get($key)) && ($site->isNotEmpty() && !A::has(self::DEFAULT_VALUES, $site->value))) { // Site globals
      return $site;
    }

    if ($option = option("tobimori.seo.default.{$key}")) { // Options fallback
      if (is_callable($option)) {
        $option = $option($this->page);
      }

      if (is_a($option, 'Kirby\Content\Field')) {
        return $option;
      }

      return new Field($this->page, $key, $option);
    }

    return new Field($this->page, $key, '');
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
      $username = $matches['name'];
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
}
