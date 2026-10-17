<?php

namespace tobimori\Seo\Sitemap;

use DOMDocument;
use DOMElement;
use DOMNode;
use Kirby\Exception\Exception;

use function array_key_exists;

class SitemapUrl
{
	protected string $lastmod;
	protected string $changefreq;
	protected string $priority;
	/** @var list<array<string, string>> */
	protected array $alternates = [];

	public function __construct(protected string $loc)
	{
	}

	/**
	 * @return ($url is null ? string : SitemapUrl)
	 */
	public function loc(string|null $url = null): SitemapUrl|string
	{
		if ($url === null) {
			return $this->loc;
		}

		$this->loc = $url;
		return $this;
	}

	/**
	 * @return ($lastmod is null ? string : SitemapUrl)
	 */
	public function lastmod(int|null $lastmod = null): SitemapUrl|string
	{
		if ($lastmod === null) {
			return $this->lastmod;
		}

		$this->lastmod = date('c', $lastmod);
		return $this;
	}

	/**
	 * @return ($changefreq is null ? string : SitemapUrl)
	 */
	public function changefreq(string|null $changefreq = null): SitemapUrl|string
	{
		if ($changefreq === null) {
			return $this->changefreq;
		}

		$this->changefreq = $changefreq;
		return $this;
	}

	/**
	 * @return ($priority is null ? string : SitemapUrl)
	 */
	public function priority(string|null $priority = null): SitemapUrl|string
	{
		if ($priority === null) {
			return $this->priority;
		}

		$this->priority = $priority;
		return $this;
	}

	/**
	 * @param list<array<string, string>> $alternates
	 * @return ($alternates is array{} ? list<array<string, string>> : SitemapUrl)
	 */
	public function alternates(array $alternates = []): SitemapUrl|array
	{
		if (empty($alternates)) {
			return $this->alternates;
		}

		foreach ($alternates as $alternate) {
			foreach (['href', 'hreflang'] as $key) {
				if (!array_key_exists($key, $alternate)) {
					throw new Exception("[Kirby SEO] The alternate link to '{$this->loc}' is missing the '{$key}' attribute");
				}
			}
		}

		$this->alternates = $alternates;
		return $this;
	}

	public function toDOMNode(DOMDocument $doc = new DOMDocument('1.0', 'UTF-8')): DOMNode
	{
		$doc->formatOutput = true;

		/** @var DOMElement $node */
		$node = $doc->createElement('url');

		foreach (['loc', 'lastmod', 'changefreq', 'priority'] as $key) {
			if (!isset($this->$key)) {
				continue;
			}

			// the value of `createElement()` is not escaped, a `&` would start an entity
			/** @var DOMElement $element */
			$element = $doc->createElement($key);
			$element->textContent = $this->$key;
			$node->appendChild($element);
		}

		foreach ($this->alternates as $alternate) {
			/** @var DOMElement $alternateNode */
			$alternateNode = $doc->createElement('xhtml:link');
			foreach ($alternate as $key => $value) {
				$alternateNode->setAttribute($key, $value);
			}

			$node->appendChild($alternateNode);
		}

		return $node;
	}

	public function toString(): string
	{
		$doc = new DOMDocument('1.0', 'UTF-8');
		$doc->formatOutput = true;

		$node = $this->toDOMNode($doc);
		$doc->appendChild($node);

		return $doc->saveXML($node) ?: throw new Exception('[Kirby SEO] Could not render the sitemap URL');
	}
}
