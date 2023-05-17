<?php

namespace tobimori\Seo;

use Kirby\Cms\Field;
use Kirby\Cms\Page;

class Meta
{
  protected Page $page;
  protected ?string $lang;
  protected array $meta = [];

  public function __call($name, $args): mixed
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
    if (($field = $this->page->content($this->lang)->get($key)) && ($field->isNotEmpty() && $field->value() !== '[]')) { // Page field
      return $field;
    }

    if ($this->meta && array_key_exists($key, $this->meta)) { // Programmatic content
      $val = $this->meta[$key];

      if (is_a($val, 'Kirby\Cms\Field')) {
        return $val;
      }

      if (is_callable($val)) {
        $val = $val($this->page);
      }

      return new Field($this->page, $key, $val);
    }

    if (($parent = $this->page->parent()) && in_array($key, $parent->metaInherit()->split())) { // Inheritance from parent
      $parentMeta = new Meta($parent, $this->lang);
      if ($value = $parentMeta->get($key)) {
        return $value;
      }
    }

    if (($site = $this->page->site()->content($this->lang)->get($key)) && ($site->isNotEmpty() && $site->value() !== '[]')) { // Site globals
      return $site;
    }

    if ($option = $this->page->kirby()->option("tobimori.seo.default.{$key}")) { // Options fallback
      if (is_callable($option)) {
        $option = $option($this->page);
      }

      if (is_a($option, 'Kirby\Cms\Field')) {
        return $option;
      }

      return new Field($this->page, $key, $option);
    }

    return new Field($this->page, $key, '');
  }

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

  public function canonicalUrl()
  {
    if (option('tobimori.seo.canonicalIncludesWWW') === false) {
      return preg_replace(array('/http:/', '/www\./'), array('https:', ''), $this->page->url());
    } else {
      return preg_replace('/http(s)?:\/\/(www.)?/', 'https://www.', $this->page->url());
    }
  }

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

  public function dateFormat()
  {
    if ($custom = $this->kirby()->option('tobimori.seo.dateFormat')) {
      return $custom;
    }

    switch ($this->kirby()->option('date.handler')) {
      case 'strftime':
        return '%%Y-%m-%d';
      case 'itl':
        return 'yyyy-MM-dd';
      case 'date':
      default:
        return 'Y-m-d';
    }
  }
}
