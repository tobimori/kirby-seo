<?php

namespace tobimori\Seo\Sitemap;

use Kirby\Cms\Page;
use Kirby\Exception\NotFoundException;
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
		return Sitemap::toXml('sitemapindex', $this);
	}

	public function isValidIndex(?string $key = null): bool
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

	/**
	 * Renders the sitemap index or a single sitemap
	 *
	 * @throws \Kirby\Exception\NotFoundException if the requested index does not exist
	 */
	public function render(Page $page): string
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

		if (!$this->isValidIndex($index->value())) {
			throw new NotFoundException("[Kirby SEO] Sitemap index '{$index->value()}' does not exist");
		}

		return $this->findBy('key', $index->value())->toString();
	}
}
