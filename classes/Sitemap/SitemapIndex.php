<?php

namespace tobimori\Seo\Sitemap;

use Kirby\Cms\App;
use Kirby\Cms\Page;
use Kirby\Content\Field;
use Kirby\Exception\NotFoundException;
use Kirby\Toolkit\Collection;

/**
 * @extends \Kirby\Toolkit\Collection<\tobimori\Seo\Sitemap\Sitemap>
 * @consistent-constructor
 */
class SitemapIndex extends Collection
{
	/** @var static|null */
	protected static $instance = null;

	public static function instance(): static
	{
		if (static::$instance === null) {
			static::$instance = new static();
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

	public function isValidIndex(string|null $key = null): bool
	{
		if ($key === null) {
			return $this->count() > 1;
		}

		return !!$this->findBy('key', $key) && $this->count() > 1;
	}

	public function generate(): void
	{
		$generator = App::instance()->option('tobimori.seo.sitemap.generator');
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

		/** @var Field $field */
		$field = $page->content()->get('index');
		$index = $field->toString();

		if ($index === '') {
			// If there is only one index, we do not need to render the index page
			// @mago-expect analysis:possible-method-access-on-null
			return $this->count() > 1 ? $this->toString() : $this->first()->toString();
		}

		$sitemap = $this->count() > 1 ? $this->findBy('key', $index) : null;
		if ($sitemap === null) {
			throw new NotFoundException("[Kirby SEO] Sitemap index '{$index}' does not exist");
		}

		return $sitemap->toString();
	}
}
