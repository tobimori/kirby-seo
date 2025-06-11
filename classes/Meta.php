<?php

namespace tobimori\Seo;

use Kirby\Cms\App;
use Kirby\Cms\FileVersion;
use Kirby\Cms\Page;
use Kirby\Content\Field;
use Kirby\Exception\InvalidArgumentException;
use Kirby\Toolkit\A;
use Kirby\Toolkit\Str;
use Kirby\Cms\Language;

/**
 * The Meta class is responsible for handling the meta data & cascading
 */
class Meta
{
	/**
	 * These values will be handled as 'field is empty'
	 */
	public const DEFAULT_VALUES = ['[]', 'default'];

	protected Page $page;
	protected ?Language $lang;
	protected array $consumed = [];
	protected array $metaDefaults = [];
	protected array $metaArray = [];

	/**
	 * Creates a new Meta instance
	 */
	public function __construct(Page $page, ?Language $lang = null)
	{
		$this->page = $page;
		$this->lang = $lang ?? kirby()->language();

		if (method_exists($this->page, 'metaDefaults')) {
			$this->metaDefaults = $this->page->metaDefaults($this->lang?->code());
		}
	}

	/**
	 * Normalize a locale string to use a specific separator
	 *
	 * @param string $locale The locale string (e.g., 'en_US.UTF-8', 'en-US', 'en_US')
	 * @param string $separator The separator to use ('-' for BCP47/hreflang, '_' for Open Graph)
	 * @return string The normalized locale (e.g., 'en-US' or 'en_US')
	 */
	public static function normalizeLocale(string $locale, string $separator = '-'): string
	{
		// encoding suffix if present (e.g., '.UTF-8')
		$locale = Str::contains($locale, '.') ? Str::before($locale, '.') : $locale;

		// target both underscores and hyphens
		$locale = Str::replace($locale, '_', $separator);
		$locale = Str::replace($locale, '-', $separator);

		return $locale;
	}

	/**
	 * Convert a Language to BCP 47 language tag format for hreflang attributes
	 *
	 * @param Language $language
	 * @return string The BCP 47 compliant language tag (e.g., 'en-US', 'de-DE')
	 */
	public static function toBCP47(Language $language): string
	{
		return self::normalizeLocale($language->locale(LC_ALL), '-');
	}

	/**
	 * Convert a Language to Open Graph locale format
	 *
	 * @param Language $language
	 * @return string The Open Graph locale format (e.g., 'en_US', 'de_DE')
	 */
	public static function toOpenGraphLocale(Language $language): string
	{
		return self::normalizeLocale($language->locale(LC_ALL), '_');
	}

	/**
	 * Returns the meta array which maps meta tags to their fieldnames
	 */
	protected function metaArray(): array
	{
		if ($this->metaArray) {
			return $this->metaArray;
		}

		// We have to specify field names and resolve them later, so we can use this
		// function to resolve meta tags from field names in the programmatic function
		$meta =
			[
				'title' => 'metaTitle',
				'description' => 'metaDescription',
				'date' => fn () => $this->page->modified($this->dateFormat()),
				'og:title' => 'ogTitle',
				'og:description' => 'ogDescription',
				'og:site_name' => 'ogSiteName',
				'og:image' => 'ogImage',
				'og:image:width' => fn () => $this->ogImageThumb()?->width() ?? null,
				'og:image:height' => fn () => $this->ogImageThumb()?->height() ?? null,
				'og:image:alt' => fn () => $this->get('ogImage')->toFile()?->alt() ?? null,
				'og:type' => 'ogType',
			];


		// Robots
		if ($robotsActive = Seo::option('robots.active')) {
			$meta['robots'] = fn () => $this->robots();
		}

		// only add canonical and alternate tags if the page is indexable
		// we have to resolve this lazily (using a callable) to avoid an infinite loop
		$allowsIndexFn = fn () => !$robotsActive || !Str::contains($this->robots(), 'noindex');

		// canonical
		$canonicalFn = fn () => $allowsIndexFn() ? $this->canonicalUrl() : null;
		$meta['canonical'] = $canonicalFn;
		$meta['og:url'] = $canonicalFn;

		// Check if the current URL is canonical
		// Compare the current request URL with the canonical URL
		$currentUrl = kirby()->request()->url()->toString();
		$canonicalUrl = $this->canonicalUrl();
		$isCanonical = $currentUrl === $canonicalUrl;

		// Multi-lang alternate tags
		// Skip hreflang tags if URL is not canonical (has query params, Kirby params, etc.)
		if (kirby()->languages()->count() > 1 && $this->lang !== null && $isCanonical) {
			foreach (kirby()->languages() as $lang) {
				// only if this language is translated for this page and exists
				// note: can be checked now, does not cause infinite loop
				if (!$this->page->translation($lang->code())->exists()) {
					continue;
				}

				// only add alternate tags if the page is indexable
				$meta['alternate'][] = fn () => $allowsIndexFn() ? [

					'hreflang' => Meta::toBCP47($lang),
					'href' => $this->page->url($lang->code()),
					'rel' => 'alternate',
				] : null;

				if ($lang !== $this->lang) {
					$meta['og:locale:alternate'][] = fn () => Meta::toOpenGraphLocale($lang);
				}
			}

			// only add alternate tags if the page is indexable
			$meta['alternate'][] = fn () => $allowsIndexFn() ? [
				'hreflang' => 'x-default',
				// use 'index' to get the x-default without language
				// https://forum.getkirby.com/t/multilanguage-how-to-get-the-siteurl-without-the-language-slug/26376/2?u=leo_portatour
				// Google: "fallback page for unmatched languages, especially on language/country selectors or auto-redirecting home pages."
				// https://developers.google.com/search/docs/specialty/international/localized-versions#all-method-guidelines
				'href' => $this->page->url('index'),
				'rel' => 'alternate',
			] : null;
			$meta['og:locale'] = fn () => Meta::toOpenGraphLocale($this->lang);
		} else {
			// Single-language site: get locale from cascade (will fallback to 'locale' option)
			$meta['og:locale'] = fn () => Meta::normalizeLocale($this->get('locale')->value(), '_');
		}

		// If URL is not canonical, also skip og:locale:alternate tags
		if (!$isCanonical) {
			unset($meta['og:locale:alternate']);
		}

		$meta['me'] = fn () => (
			($socialMedia = $this->site('socialMediaAccounts')?->toObject())
			&& ($mastodon = $socialMedia->mastodon()->value())
		) ? $mastodon : null;

		// This array will be normalized for use in the snippet in $this->snippetData()
		return $this->metaArray = $meta;
	}

	/**
	 * This array defines what HTML tag the corresponding meta tags are using including the attributes,
	 * so everything is a bit more elegant when defining programmatic content (supports regex)
	 */
	public const TAG_TYPE_MAP = [
		[
			'tag' => 'title',
			'priority' => true,
			'tags' => [
				'title'
			]
		],
		[
			'tag' => 'link',
			'attributes' => [
				'name' => 'rel',
				'content' => 'href',
			],
			'tags' => [
				'me',
				'canonical',
				'alternate',
			]
		],
		[
			'tag' => 'meta',
			'attributes' => [
				'name' => 'property',
				'content' => 'content',
			],
			'tags' => [
				'/og:.+/'
			]
		]
	];

	/**
	 * Normalize the meta array and remaining meta defaults to be used in the snippet,
	 * also resolve the content, if necessary
	 */
	public function snippetData(?array $raw = null): array
	{
		$mergeWithDefaults = !isset($raw);
		$raw ??= $this->metaArray();
		$tags = [];

		foreach ($raw as $name => $value) {
			// if the key is numeric, it is already normalized to the correct array syntax
			if (is_numeric($name)) {
				// but we still check if the array is valid
				if (!is_array($value) || count(array_intersect(['tag', 'content', 'attributes'], array_keys($value))) !== count($value)) {
					throw new InvalidArgumentException("[Kirby SEO] Invalid array structure found in programmatic content for page {$this->slug()}. Please check your metaDefaults method for template {$this->template()->name()}.");
				}

				$tags[] = $value;
				continue;
			}

			// allow overrides from metaDefaults for keys that are a callable or array by default
			// (all fields from meta array that are not part of the regular cascade)
			if ((is_callable($value) || is_array($value)) && $mergeWithDefaults && array_key_exists($name, $this->metaDefaults)) {
				$this->consumed[] = $name;
				$value = $this->metaDefaults[$name];
			}

			// if the value is a string, we know it's a field name
			if (is_string($value)) {
				$value = $this->$value($name);
			}

			// if the value is a callable, we resolve it
			if (is_callable($value)) {
				$value = $value($this->page);
			}

			// if the value is empty, we don't want to output it
			if ((is_a($value, 'Kirby\Content\Field') && $value->isEmpty()) || !$value) {
				continue;
			}

			// resolve the tag type from the meta array
			// so we can use the correct attributes to normalize it
			$tag = $this->resolveTag($name);

			// if the value is an associative array now, all of them are attributes
			// and we don't look for what the TAG_TYPE_MAP says
			// or there should be multiple tags with the same name (non-associative array)
			if (is_array($value)) {
				if (!A::isAssociative($value)) {
					foreach ($value as $val) {
						$tags = array_merge($tags, $this->snippetData([$name => $val]));
					}
					continue;
				}

				// array is associative, so it's an array of attributes
				// we resolve the values, if they are callable
				array_walk($value, function (&$item) {
					if (is_callable($item)) {
						$item = $item($this->page);
					}
				});

				// add the tag to the array
				$tags[] = [
					'tag' => $tag['tag'],
					'attributes' => $value,
					'content' => null,
					'priority' => $tag['priority'] ?? false,
				];
				continue;
			}

			// if the value is a string, we use the TAG_TYPE_MAP
			// to correctly map the attributes
			$tags[] = [
				'tag' => $tag['tag'],
				'attributes' => isset($tag['attributes']) ? [
					$tag['attributes']['name'] => $name,
					$tag['attributes']['content'] => $value,
				] : null,
				'content' => !isset($tag['attributes']) ? $value : null,
				'priority' => $tag['priority'] ?? false,
			];
		}

		if ($mergeWithDefaults) {
			// merge the remaining meta defaults
			$tags = array_merge($tags, $this->snippetData(array_diff_key($this->metaDefaults, array_flip($this->consumed))));
		}

		return $tags;
	}

	/**
	 * Resolves the tag type from the meta array
	 */
	protected function resolveTag(string $tag): array
	{
		foreach (self::TAG_TYPE_MAP as $type) {
			foreach ($type['tags'] as $regexOrString) {
				// Check if the supplied tag is a regex or a normal tag name
				if (Str::startsWith($regexOrString, '/') && Str::endsWith($regexOrString, '/') ?
					Str::match($tag, $regexOrString) : $tag === $regexOrString
				) {
					return $type;
				}
			}
		}

		return [
			'tag' => 'meta',
			'attributes' => [
				'name' => 'name',
				'content' => 'content',
			]
		];
	}

	/**
	 * Magic method to get a meta value by calling the method name
	 */
	public function __call($name, $args = null): mixed
	{
		if (method_exists($this, $name)) {
			return $this->$name($args);
		}

		return $this->get($name);
	}

	/**
	 * Get the meta value for a given key
	 */
	public function get(string $key, array $exclude = []): Field
	{
		$cascade = Seo::option('cascade');
		if (count(array_intersect(get_class_methods($this), $cascade)) !== count($cascade)) {
			throw new InvalidArgumentException('[Kirby SEO] Invalid cascade method in config. Please check your options for `tobimori.seo.cascade`.');
		}

		// Track consumed keys, so we don't output legacy field values
		$toBeConsumed = $key;
		if (
			(array_key_exists($toBeConsumed, $this->metaDefaults)
				|| array_key_exists($toBeConsumed = $this->findTagForField($toBeConsumed), $this->metaDefaults))
			&& !in_array($toBeConsumed, $this->consumed)
		) {
			$this->consumed[] = $toBeConsumed;
		}

		foreach (array_diff($cascade, $exclude) as $method) {
			if ($field = $this->$method($key)) {
				return $field;
			}
		}

		return new Field($this->page, $key, '');
	}

	/**
	 * Get the meta value for a given key from the page's fields
	 */
	protected function fields(string $key): Field|null
	{
		if (($field = $this->page->content($this->lang?->code())->get($key))) {
			if (Str::contains($key, 'robots') && !Seo::option('robots.pageSettings')) {
				return null;
			}

			if ($field->isNotEmpty() && !A::has(self::DEFAULT_VALUES, $field->value())) {
				return $field;
			}
		}

		return null;
	}

	/**
	 * Maps Open Graph fields to Meta fields for fallbackFields
	 * cascade method
	 */
	public const FALLBACK_MAP = [
		'ogTitle' => 'metaTitle',
		'ogDescription' => 'metaDescription',
		'ogTemplate' => 'metaTemplate',
	];

	/**
	 * We only allow the following cascade methods for fallbacks,
	 * because we don't want to fallback to the config defaults for
	 * Meta fields, because we most likely already have those set
	 * for the Open Graph fields
	 */
	public const FALLBACK_CASCADE = [
		'fields',
		'programmatic',
		'parent',
		'site'
	];

	/**
	 * Get the meta value for a given key using the fallback fields
	 * defined above (usually Open Graph > Meta Fields)
	 */
	protected function fallbackFields(string $key): Field|null
	{
		if (array_key_exists($key, self::FALLBACK_MAP)) {
			$fallback = self::FALLBACK_MAP[$key];
			$cascade = Seo::option('cascade');

			foreach (array_intersect($cascade, self::FALLBACK_CASCADE) as $method) {
				if ($field = $this->$method($fallback)) {
					return $field;
				}
			}
		}

		return null;
	}

	protected function findTagForField(string $fieldName): string|null
	{
		return array_search($fieldName, $this->metaArray());
	}

	/**
	 * Get the meta value for a given key from the page's meta
	 * array, which can be set in the page's model metaDefaults method
	 */
	protected function programmatic(string $key): Field|null
	{
		if (!$this->metaDefaults) {
			return null;
		}

		// Check if the key (field name) is in the array syntax
		if (array_key_exists($key, $this->metaDefaults)) {
			$val = $this->metaDefaults[$key];
		}

		/* If there is no programmatic value for the key,
		 * try looking it up in the meta array
		 * maybe it is a meta tag and not a field name?
		 */
		if (!isset($val) && ($key = $this->findTagForField($key)) && array_key_exists($key, $this->metaDefaults)) {
			$val = $this->metaDefaults[$key];
		}

		if (isset($val)) {
			if (is_callable($val)) {
				$val = $val($this->page);
			}

			if (is_array($val)) {
				$val = $val['content'] ?? $val['href'] ?? null;

				// Last sanity check, if the array syntax doesn't have a supported key
				if ($val === null) {
					// Remove the key from the consumed array, so it doesn't get filtered out
					// (we can assume the entry is a custom meta tag that uses different attributes)
					$this->consumed = array_filter($this->consumed, fn ($item) => $item !== $key);
					return null;
				}
			}

			if (is_a($val, 'Kirby\Content\Field')) {
				return new Field($this->page, $key, $val->value());
			}

			return new Field($this->page, $key, $val);
		}

		return null;
	}

	/**
	 * Get the meta value for a given key from the page's parent,
	 * if the page is allowed to inherit the value
	 */
	protected function parent(string $key): Field|null
	{
		if ($this->canInherit($key)) {
			$parent = $this->page->parent();
			$parentMeta = new Meta($parent, $this->lang);
			if ($value = $parentMeta->get($key)) {
				return $value;
			}
		}

		return null;
	}

	/**
	 * Get the meta value for a given key from the
	 * site's meta blueprint & content
	 */
	protected function site(string $key): Field|null
	{
		if (($site = $this->page->site()->content($this->lang?->code())->get($key)) && ($site->isNotEmpty() && !A::has(self::DEFAULT_VALUES, $site->value))) {
			return $site;
		}

		return null;
	}

	/**
	 * Get the meta value for a given key from the
	 * config.php options
	 */
	protected function options(string $key): Field|null
	{
		if ($option = Seo::option("default.{$key}", args: [$this->page])) {
			if (is_a($option, 'Kirby\Content\Field')) {
				return $option;
			}

			return new Field($this->page, $key, $option);
		}

		return null;
	}

	/**
	 * Checks if the page can inherit a meta value from its parent
	 */
	private function canInherit(string $key): bool
	{
		$parent = $this->page->parent();
		if (!$parent) {
			return false;
		}

		$inherit = $parent->metaInherit()->split();
		if (Str::contains($key, 'robots') && A::has($inherit, 'robots')) {
			return true;
		}
		return A::has($inherit, $key);
	}

	/**
	 * Applies the title template, and returns the correct title
	 */
	public function metaTitle()
	{
		$title = $this->get('metaTitle');
		$template = $this->get('metaTemplate');

		$useTemplate = $this->page->useTitleTemplate();
		$useTemplate = $useTemplate->isEmpty() ? true : $useTemplate->toBool();

		$string = $title->value();
		if ($useTemplate) {
			$string = $this->page->toString(
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
		$title = $this->get('metaTitle');
		$template = $this->get('ogTemplate');

		$useTemplate = $this->page->useOgTemplate();
		$useTemplate = $useTemplate->isEmpty() ? true : $useTemplate->toBool();

		$string = $title->value();
		if ($useTemplate) {
			$string = $this->page->toString(
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
		return $this->page->site()->canonicalFor($this->page->url());
	}

	/**
	 * Gets the date format for modified meta tags, based on the registered date handler
	 */
	public function dateFormat(): string
	{
		if ($custom = Seo::option('dateFormat')) {
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
		foreach (Seo::option('robots.types') as $type) {
			if (!$this->get('robots' . Str::ucfirst($type))->toBool()) {
				$robots[] = 'no' . Str::lower($type);
			}
		}

		if (A::count($robots) === 0) {
			$robots = ['all'];
		}

		return A::join($robots, ',');
	}

	/**
	 * Get the og:image thumb object
	 */
	public function ogImageThumb(): FileVersion|null
	{
		$field = $this->get('ogImage');

		// Only process if we have a file object
		if ($file = $field->toFile()) {
			$cropOgImage = $this->get('cropOgImage')->toBool();

			if ($cropOgImage) {
				// Crop to 1200x630
				return $file->thumb([
					'width' => 1200,
					'height' => 630,
					'crop' => true,
				]);
			} else {
				// Resize to max 1500px on the longest side
				return $file->thumb([
					'width' => 1500,
					'height' => 1500,
					'upscale' => false,
				]);
			}
		}

		// Return null if it's a custom URL or empty
		return null;
	}

	/**
	 * Get the og:image url
	 */
	public function ogImage(): string|null
	{
		if ($ogImage = $this->ogImageThumb()) {
			return $ogImage->url();
		}

		$field = $this->get('ogImage');
		if ($field->isNotEmpty()) {
			return $field->value();
		}

		return null;
	}

	/**
	 * Helper method the get the current page from the URL path,
	 * for use in programmatic blueprints
	 */
	public static function currentPage(): Page|null
	{
		$path = App::instance()->request()->url()->toString();
		$matches = Str::match($path, "/pages\/([a-zA-Z0-9-_+]+)\/?/m");
		$segments = Str::split($matches[1], '+');

		$page = App::instance()->site();
		foreach ($segments as $segment) {
			if ($page = $page->findPageOrDraft($segment)) {
				continue;
			}

			return null;
		}

		return $page;
	}
}
