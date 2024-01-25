<?php

namespace tobimori\Seo\Sitemap;

use DOMDocument;
use DOMNode;
use Kirby\Exception\Exception;

class SitemapUrl
{
	protected string $lastmod;
	protected string $changefreq;
	protected string $priority;
	protected array $alternates = [];

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

	public function alternates(array $alternates = []): SitemapUrl|array
	{
		if (empty($alternates)) {
			return $this->alternates;
		}

		foreach ($alternates as $alternate) {
			foreach (['href', 'hreflang'] as $key) {
				if (!array_key_exists($key, $alternate)) {
					new Exception("[kirby-seo] The alternate link to '{$this->loc()} is missing the '{$key}' attribute");
				}
			}
		}


		$this->alternates = $alternates;
		return $this;
	}

	public function toDOMNode(DOMDocument $doc = new DOMDocument('1.0', 'UTF-8')): DOMNode
	{
		$doc->formatOutput = true;

		$node = $doc->createElement('url');

		foreach (array_diff_key(get_object_vars($this), array_flip(['alternates'])) as $key => $value) {
			$node->appendChild($doc->createElement($key, $value));
		}

		if (!empty($this->alternates())) {
			foreach ($this->alternates() as $alternate) {
				$alternateNode = $doc->createElement('xhtml:link');
				foreach ($alternate as $key => $value) {
					$alternateNode->setAttribute($key, $value);
				}

				$node->appendChild($alternateNode);
			}
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
