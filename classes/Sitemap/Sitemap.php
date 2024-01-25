<?php

namespace tobimori\Seo\Sitemap;

use DOMDocument;
use Kirby\Cms\App;
use Kirby\Toolkit\Collection;

class Sitemap extends Collection
{
	public function __construct(protected string $key, array $data = [], bool $caseSensitive = false)
	{
		parent::__construct($data, $caseSensitive);
	}

	public function key(): string
	{
		return $this->key;
	}

	public function loc(): string
	{
		return kirby()->site()->canonicalFor('sitemap-' . $this->key . '.xml');
	}

	public function lastmod(): string
	{
		$lastmod = 0;
		foreach ($this as $url) {
			$lastmod = max($lastmod, strtotime($url->lastmod()));
		}

		if ($lastmod > 0) {
			return date('c', $lastmod);
		}

		return date('c');
	}

	public function createUrl(string $loc): SitemapUrl
	{
		$url = $this->makeUrl($loc);
		$this->append($url);
		return $url;
	}

	public static function makeUrl(string $url): SitemapUrl
	{
		return new SitemapUrl($url);
	}

	public function toDOMNode(DOMDocument $doc = new DOMDocument('1.0', 'UTF-8'))
	{
		$doc->formatOutput = true;

		$root = $doc->createElement('sitemap');
		foreach (['loc', 'lastmod'] as $key) {
			$root->appendChild($doc->createElement($key, $this->$key()));
		}

		return $root;
	}

	public function toString(): string
	{
		$doc = new DOMDocument('1.0', 'UTF-8');
		$doc->formatOutput = true;

		$doc->appendChild($doc->createProcessingInstruction('xml-stylesheet', 'type="text/xsl" href="/sitemap.xsl"'));

		$root = $doc->createElementNS('http://www.sitemaps.org/schemas/sitemap/0.9', 'urlset');
		$root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xhtml', 'http://www.w3.org/1999/xhtml');
		$root->setAttribute('seo-version', App::plugin('tobimori/seo')->version());

		foreach ($this as $url) {
			$root->appendChild($url->toDOMNode($doc));
		}

		$doc->appendChild($root);
		return $doc->saveXML();
	}
}
