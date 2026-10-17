<?php

namespace tobimori\Seo\Sitemap;

use DOMDocument;
use DOMElement;
use DOMProcessingInstruction;
use Kirby\Cms\App;
use Kirby\Exception\Exception;
use Kirby\Toolkit\Collection;

/**
 * @extends \Kirby\Toolkit\Collection<\tobimori\Seo\Sitemap\SitemapUrl>
 */
class Sitemap extends Collection
{
	/**
	 * @param array<string, \tobimori\Seo\Sitemap\SitemapUrl> $data
	 */
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
		return App::instance()
			->site()
			->canonicalFor('sitemap-' . $this->key . '.xml', true);
	}

	public function lastmod(): string
	{
		$lastmod = 0;
		foreach ($this as $url) {
			$lastmod = max($lastmod, strtotime($url->lastmod()) ?: 0);
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

	/**
	 * @return DOMElement
	 */
	public function toDOMNode(DOMDocument $doc = new DOMDocument('1.0', 'UTF-8'))
	{
		$doc->formatOutput = true;

		/** @var DOMElement $root */
		$root = $doc->createElement('sitemap');

		/** @var DOMElement $loc */
		$loc = $doc->createElement('loc', $this->loc());
		$root->appendChild($loc);

		/** @var DOMElement $lastmod */
		$lastmod = $doc->createElement('lastmod', $this->lastmod());
		$root->appendChild($lastmod);

		return $root;
	}

	public function toString(): string
	{
		return static::toXml('urlset', $this);
	}

	/**
	 * Renders a sitemap document with the stylesheet and the
	 * DOM nodes of the given items in the root element
	 *
	 * @param iterable<Sitemap|SitemapUrl> $items
	 */
	public static function toXml(string $root, iterable $items): string
	{
		$doc = new DOMDocument('1.0', 'UTF-8');
		$doc->formatOutput = true;

		$stylesheetUrl = (string)App::instance()->url() . '/sitemap.xsl';

		/** @var DOMProcessingInstruction $stylesheet */
		$stylesheet = $doc->createProcessingInstruction('xml-stylesheet', 'type="text/xsl" href="' . $stylesheetUrl . '"');
		$doc->appendChild($stylesheet);

		/** @var DOMElement $element */
		$element = $doc->createElementNS('http://www.sitemaps.org/schemas/sitemap/0.9', $root);
		$element->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xhtml', 'http://www.w3.org/1999/xhtml');

		// version can be null when installing branches during development
		if ($version = App::plugin('tobimori/seo')?->version()) {
			$element->setAttribute('seo-version', $version);
		}

		foreach ($items as $item) {
			$element->appendChild($item->toDOMNode($doc));
		}

		$doc->appendChild($element);
		return $doc->saveXML() ?: throw new Exception('[Kirby SEO] Could not render the sitemap');
	}
}
