<?php

namespace tobimori\Seo\Sitemap;

use DOMDocument;
use DOMNode;

class SitemapUrl
{
  protected string $lastmod;
  protected string $changefreq;
  protected string $priority;

  public function __construct(protected string $loc)
  {
  }

  public function loc(string $url = null): SitemapUrl|string
  {
    if ($url === null) {
      return $this->loc;
    }

    $this->loc = $url;
    return $this;
  }

  public function lastmod(string $lastmod = null): SitemapUrl|string
  {
    if ($lastmod === null) {
      return $this->lastmod;
    }

    $this->lastmod = date('c', $lastmod);
    return $this;
  }

  public function changefreq(string $changefreq = null): SitemapUrl|string
  {
    if ($changefreq === null) {
      return $this->changefreq;
    }

    $this->changefreq = $changefreq;
    return $this;
  }

  public function priority(string $priority = null): SitemapUrl|string
  {
    if ($priority === null) {
      return $this->priority;
    }

    $this->priority = $priority;
    return $this;
  }

  public function toDOMNode(DOMDocument $doc = new DOMDocument('1.0', 'UTF-8')): DOMNode
  {
    $doc->formatOutput = true;

    $node = $doc->createElement('url');

    foreach (array_diff(get_object_vars($this), ['alternates']) as $key => $value) {
      $node->appendChild($doc->createElement($key, $value));
    }

    return $node;
  }

  public function toString(): string
  {
    $doc = new DOMDocument('1.0', 'UTF-8');
    $doc->formatOutput = true;

    $node = $this->toDOMNode();
    $doc->appendChild($node);

    return $doc->saveXML($node);
  }
}
