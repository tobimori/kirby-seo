<?php

namespace tobimori\Seo\Sitemap;

use DOMDocument;
use Kirby\Cms\App;
use Kirby\Cms\Page;
use Kirby\Toolkit\Collection;

class SitemapIndex extends Collection
{
	protected static $instance = null;

	public static function instance(...$args): static
	{
		if (static::$instance === null) {
			static::$instance = new static(...$args);
		}

		return static::$instance;
	}

	public function create(string $key = 'pages'): Sitemap
	{
		$sitemap = $this->make($key);
		$this->append($sitemap);
		return $sitemap;
	}

	public static function make(string $key = 'pages'): Sitemap
	{
		return new Sitemap($key);
	}

	public static function makeUrl(string $url): SitemapUrl
	{
		return new SitemapUrl($url);
	}

	public function toString(): string
	{
		$doc = new DOMDocument('1.0', 'UTF-8');
		$doc->formatOutput = true;

		$doc->appendChild($doc->createProcessingInstruction('xml-stylesheet', 'type="text/xsl" href="sitemap.xsl"'));

		$root = $doc->createElementNS('http://www.sitemaps.org/schemas/sitemap/0.9', 'sitemapindex');
		$root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xhtml', 'http://www.w3.org/1999/xhtml');
		$root->setAttribute('seo-version', App::plugin('tobimori/seo')->version());
		$doc->appendChild($root);

		foreach ($this as $sitemap) {
			$root->appendChild($sitemap->toDOMNode($doc));
		}

		return $doc->saveXML();
	}

	public function isValidIndex(string $key = null): bool
	{
		if ($key === null) {
			return $this->count() > 1;
		}

		return !!$this->findBy('key', $key) && $this->count() > 1;
	}

	public function generate(): void
	{
		$generator = option('tobimori.seo.sitemap.generator');
		if (is_callable($generator)) {
			$generator($this);
		}
	}

	public function render(Page $page): string|null
	{
		// There always has to be at least one index,
		// otherwise the sitemap will fail to render
		if ($this->count() === 0) {
			$this->generate();
		}

		if ($this->count() === 0) {
			$this->create();
		}

		if (($index = $page->content()->get('index'))->isEmpty()) {
			// If there is only one index, we do not need to render the index page
			return $this->count() > 1 ? $this->toString() : $this->first()->toString();
		}

		$sitemap = $this->findBy('key', $index->value());
		if ($sitemap) {
			return $sitemap->toString();
		}

		return null;
	}
}
