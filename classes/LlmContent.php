<?php

namespace tobimori\Seo;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Kirby\Cms\Language;
use Kirby\Cms\Page;
use Kirby\Cms\Pages;
use Kirby\Exception\NotFoundException;
use Kirby\Filesystem\F;
use Kirby\Http\Response;
use Kirby\Http\Uri;
use Kirby\Toolkit\A;
use Kirby\Toolkit\Str;
use Throwable;

/**
 * Provides the Markdown representation for one page.
 */
class LlmContent
{
	public const MARKDOWN_TYPE = 'text/markdown';

	protected ?bool $available = null;

	public function __construct(protected Page $page)
	{
	}

	public function enabled(): bool
	{
		return Seo::option('agentic.enabled', true) === true
			&& Seo::option('agentic.markdown.enabled', true) === true;
	}

	public function llmsTxtEnabled(): bool
	{
		return Seo::option('agentic.enabled', true) === true
			&& Seo::option('agentic.llmsTxt.enabled', true) === true;
	}

	/**
	 * Select Markdown only if the Accept header prefers it over HTML.
	 */
	public function prefersMarkdown(string|null $accept): bool
	{
		$quality = [self::MARKDOWN_TYPE => 0.0, 'text/html' => 0.0];

		foreach (Str::accepted(Str::lower($accept ?? '')) as $type) {
			foreach ($quality as $mime => $value) {
				if (in_array($type['value'], [$mime, 'text/*', '*/*'], true)) {
					$quality[$mime] = max($value, $type['quality']);
				}
			}
		}

		return $quality[self::MARKDOWN_TYPE] > $quality['text/html'];
	}

	protected function hasExplicitRepresentation(): bool
	{
		try {
			return $this->page->representation('md')->exists();
		} catch (NotFoundException) {
			return false;
		}
	}

	public function available(): bool
	{
		return $this->available ??= $this->enabled()
			&& $this->page->isPublished()
			&& ($this->hasExplicitRepresentation() || ($this->automaticConversionEnabled() && $this->converter() !== null));
	}

	/**
	 * Render an explicit representation first. Use conversion second.
	 */
	public function render(string|null $html = null): string|null
	{
		if ($this->enabled() === false || $this->page->isPublished() === false) {
			return null;
		}

		if ($this->hasExplicitRepresentation()) {
			$markdown = trim($this->page->render(contentType: 'md'));
			return $markdown === '' ? null : $markdown . "\n";
		}

		if ($this->automaticConversionEnabled() === false) {
			return null;
		}

		$html ??= $this->page->render();
		return $this->convert($html);
	}

	public function markdownUrl(Language|string|null $language = null): string
	{
		$languageCode = $language instanceof Language ? $language->code() : $language;
		$url = $this->page->url($languageCode);

		if ($this->page->isHomePage()) {
			$uri = $this->page->uri($languageCode);
			return Str::rtrim($this->page->site()->url($languageCode), '/') . '/' . $uri . '.md';
		}

		return Str::rtrim($url, '/') . '.md';
	}

	protected function canonicalUrl(): string
	{
		return $this->page->metadata()->canonicalUrl();
	}

	public function markdownResponse(Language|null $language = null, string $method = 'GET'): Response|null
	{
		if (($markdown = $this->render()) === null) {
			return null;
		}

		$link = '<' . $this->canonicalUrl() . '>; rel="canonical"';
		if ($this->llmsTxtEnabled()) {
			$link .= ', <' . $this->llmsTxtUrl($language) . '>; rel="describedby"';
		}

		$headers = [
			'Content-Location' => $this->markdownUrl($language),
			'Link' => $link,
			'Vary' => 'Accept',
			'X-Content-Type-Options' => 'nosniff',
			'X-Robots-Tag' => 'noindex',
		];
		if ($language !== null) {
			$headers['Content-Language'] = $language->code();
		}

		return new Response(
			Str::upper($method) === 'HEAD' ? '' : $markdown,
			self::MARKDOWN_TYPE,
			200,
			$headers
		);
	}

	public function notFoundResponse(Language|null $language = null, string $method = 'GET'): Response
	{
		$markdown = $this->render();
		if ($markdown === null) {
			$markdown = trim($this->page->kirby()->template('error', 'md')->render([
				'page' => $this->page,
				'site' => $this->page->site(),
			])) . "\n";
		}

		$headers = [
			'Vary' => 'Accept',
			'X-Content-Type-Options' => 'nosniff',
			'X-Robots-Tag' => 'noindex',
		];
		if ($language !== null) {
			$headers['Content-Language'] = $language->code();
		}

		return new Response(
			Str::upper($method) === 'HEAD' ? '' : $markdown,
			self::MARKDOWN_TYPE,
			404,
			$headers
		);
	}

	public function llmsTxtUrl(Language|string|null $language = null): string
	{
		$languageCode = $language instanceof Language ? $language->code() : $language;
		return Str::rtrim($this->page->site()->url($languageCode), '/') . '/llms.txt';
	}

	/**
	 * Returns a virtual page for llms.txt in the current language, so the pages cache applies.
	 * The page ID mirrors the URL path (e.g. `en/llms`), so a static pages cache
	 * writes one file for each language.
	 */
	public function llmsTxtPage(): Page
	{
		$kirby = $this->page->kirby();
		$prefix = Str::after($this->page->site()->url($kirby->languageCode()), $kirby->url('index'));

		$parent = null;
		foreach (Str::split($prefix, '/') as $slug) {
			$parent = Page::factory(['slug' => $slug, 'parent' => $parent]);
		}

		return Page::factory([
			'slug' => 'llms',
			'template' => 'llms',
			'parent' => $parent,
			'content' => ['title' => 'llms.txt'],
		]);
	}

	/**
	 * Returns the data for the llms.txt template and sets the response headers
	 */
	public function llmsTxtData(): array
	{
		$kirby = $this->page->kirby();
		$language = $kirby->language();
		$pages = $this->llmsTxtPages($language?->code());

		$response = $kirby->response();
		$response->type('text/plain');
		$response->header('Link', '<' . $this->llmsTxtUrl($language) . '>; rel="canonical"');
		$response->header('X-Content-Type-Options', 'nosniff');
		if ($language !== null) {
			$response->header('Content-Language', $language->code());
		}

		return [
			'entries' => $this->llmsTxtEntries($pages, $language?->code()),
			'pages' => $pages,
		];
	}

	public function addDiscoveryHeaders(): void
	{
		$response = $this->page->kirby()->response();
		$values = Str::split($response->header('Vary'));
		$normalized = array_map(Str::lower(...), $values);
		if (!in_array('*', $normalized, true) && !in_array('accept', $normalized, true)) {
			$values[] = 'Accept';
			$response->header('Vary', A::join($values, ', '));
		}

		$links = ['<' . $this->markdownUrl() . '>; rel="alternate"; type="text/markdown"'];
		if ($this->llmsTxtEnabled()) {
			$links[] = '<' . $this->llmsTxtUrl() . '>; rel="describedby"';
		}

		$current = $response->header('Link');
		foreach ($links as $link) {
			if ($current === null) {
				$current = $link;
			} elseif (!Str::contains($current, $link)) {
				$current .= ', ' . $link;
			}
		}
		$response->header('Link', $current);
	}

	protected function automaticConversionEnabled(): bool
	{
		return Seo::option('agentic.markdown.auto', false) === true;
	}

	protected function convert(string $html): string|null
	{
		$fragment = $this->extract($html);
		if ($fragment === null || ($converter = $this->converter()) === null) {
			return null;
		}

		$fragment = $this->rewriteLocalLinks($fragment);

		try {
			if (is_callable($converter)) {
				$markdown = $converter($fragment, $this->page, $this);
			} elseif (is_object($converter) && method_exists($converter, 'convert')) {
				$markdown = $converter->convert($fragment);
			} else {
				return null;
			}
		} catch (Throwable) {
			return null;
		}

		if (!is_string($markdown) || trim($markdown) === '') {
			return null;
		}

		return trim(Str::replace($markdown, ["\r\n", "\r"], "\n")) . "\n";
	}

	protected function converter(): mixed
	{
		$converter = $this->rawOption('agentic.markdown.converter');

		if (is_string($converter) && class_exists($converter)) {
			$converter = new $converter();
		}

		if ($converter !== null) {
			return $converter;
		}

		if (class_exists('League\\HTMLToMarkdown\\HtmlConverter')) {
			return new \League\HTMLToMarkdown\HtmlConverter();
		}

		return null;
	}

	/**
	 * Extract only a configured content element. Never use the complete body.
	 */
	protected function extract(string $html): string|null
	{
		if (trim($html) === '') {
			return null;
		}

		$document = new DOMDocument();
		$previous = libxml_use_internal_errors(true);
		$loaded = $document->loadHTML(
			'<?xml encoding="UTF-8"><meta charset="UTF-8">' . $html,
			LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET
		);
		libxml_clear_errors();
		libxml_use_internal_errors($previous);

		if ($loaded === false) {
			return null;
		}

		$xpath = new DOMXPath($document);
		foreach ($this->selectors() as $selector) {
			$query = $this->selectorToXPath($selector);
			if ($query === null || ($nodes = @$xpath->query($query)) === false) {
				continue;
			}

			$node = $nodes->item(0);
			if ($node instanceof DOMElement) {
				return $document->saveHTML($node) ?: null;
			}
		}

		return null;
	}

	protected function rewriteLocalLinks(string $html): string
	{
		$document = new DOMDocument();
		$previous = libxml_use_internal_errors(true);
		$loaded = $document->loadHTML(
			'<?xml encoding="UTF-8"><html><head><meta charset="UTF-8"></head><body>' . $html . '</body></html>',
			LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET
		);
		libxml_clear_errors();
		libxml_use_internal_errors($previous);

		if ($loaded === false) {
			return $html;
		}

		foreach ($document->getElementsByTagName('a') as $link) {
			if ($markdownUrl = $this->localMarkdownUrl($link->getAttribute('href'))) {
				$link->setAttribute('href', $markdownUrl);
			}
		}

		$node = (new DOMXPath($document))->query('/html/body/*[1]')?->item(0);
		return $node instanceof DOMElement ? ($document->saveHTML($node) ?: $html) : $html;
	}

	protected function localMarkdownUrl(string $href): string|null
	{
		$href = trim($href);
		if ($href === '' || Str::startsWith($href, '#')) {
			return null;
		}

		try {
			$hrefUri = new Uri($href);
			$sourceUri = new Uri($this->page->url());
			$languageCode = $this->page->kirby()->languageCode();
			$siteUri = new Uri($this->page->site()->url($languageCode));
		} catch (Throwable) {
			return null;
		}

		if ($hrefUri->isAbsolute() && !in_array(Str::lower($hrefUri->scheme()), ['http', 'https'], true)) {
			return null;
		}

		$targetHost = $hrefUri->isAbsolute() ? $hrefUri->host() : $sourceUri->host();
		$targetPort = $hrefUri->isAbsolute() ? $hrefUri->port() : $sourceUri->port();
		if (Str::lower($targetHost) !== Str::lower($siteUri->host()) || $targetPort !== $siteUri->port()) {
			return null;
		}

		$hrefPath = $hrefUri->path()->toString();
		if ($hrefUri->isAbsolute() || Str::startsWith($href, '/')) {
			$targetPath = '/' . $hrefPath;
		} elseif ($hrefPath === '') {
			$targetPath = $sourceUri->path()->toString(true) ?: '/';
		} else {
			$sourcePath = $sourceUri->path()->toString(true) ?: '/';
			$directory = $sourceUri->slash()
				? $sourcePath
				: Str::rtrim(F::dirname($sourcePath), '/') . '/';
			$targetPath = $directory . $hrefPath;
		}

		$targetPath = $this->normalizePath($targetPath);
		$sitePath = Str::rtrim($this->normalizePath($siteUri->path()->toString(true) ?: '/'), '/');
		if ($sitePath !== '' && $targetPath !== $sitePath && !Str::startsWith($targetPath, $sitePath . '/')) {
			return null;
		}

		$route = Str::ltrim(Str::substr($targetPath, Str::length($sitePath)), '/');
		if ($route !== '' && F::extension($route) !== '') {
			return null;
		}

		try {
			$target = $this->page->kirby()->resolve($route === '' ? null : rawurldecode($route), $languageCode);
		} catch (Throwable) {
			return null;
		}

		if (!$target instanceof Page || $target->isPublished() === false) {
			return null;
		}

		$content = $this->contentFor($target);
		if ($content->available() === false) {
			return null;
		}

		$url = $content->markdownUrl($languageCode);
		if ($hrefUri->hasQuery()) {
			$url .= '?' . $hrefUri->query()->toString();
		}
		if ($hrefUri->hasFragment()) {
			$url .= '#' . $hrefUri->fragment();
		}

		return $url;
	}

	protected function llmsTxtPages(string|null $languageCode): Pages
	{
		$kirby = $this->page->kirby();
		$pages = $this->page->site()->index();
		$custom = $this->rawOption('agentic.llmsTxt.pages');
		if (is_callable($custom)) {
			$result = $custom($pages, $languageCode, $this);
			if ($result instanceof Pages) {
				$pages = $result;
			}
		}

		return $pages->filter(function (Page $page) use ($kirby, $languageCode) {
			if ($page->isPublished() === false || $page->metadata()->robotsIndex()->toBool() === false) {
				return false;
			}

			if ($kirby->multilang() && ($languageCode === null || $page->translation($languageCode)->exists() === false)) {
				return false;
			}

			return $this->contentFor($page)->available();
		});
	}

	protected function llmsTxtEntries(Pages $pages, string|null $languageCode): array
	{
		$entries = [];
		foreach ($pages as $page) {
			$entries[] = [
				'description' => $page->metadata()->metaDescription()->value(),
				'page' => $page,
				'title' => $page->title()->value(),
				'url' => $this->contentFor($page)->markdownUrl($languageCode),
			];
		}

		return $entries;
	}

	protected function contentFor(Page $page): LlmContent
	{
		$class = Seo::option('components.agentic');
		return new $class($page);
	}

	protected function normalizePath(string $path): string
	{
		$segments = [];
		foreach (Str::split($path, '/') as $segment) {
			if ($segment === '' || $segment === '.') {
				continue;
			}

			if ($segment === '..') {
				array_pop($segments);
				continue;
			}

			$segments[] = $segment;
		}

		return '/' . A::join($segments, '/');
	}

	protected function selectors(): array
	{
		$selectors = Seo::option('agentic.markdown.selectors', [
			'[data-agentic-content]',
			'main',
			'article',
		]);

		return array_values(array_filter((array)$selectors, 'is_string'));
	}

	protected function selectorToXPath(string $selector): string|null
	{
		$selector = trim($selector);
		if ($selector === '') {
			return null;
		}

		if (Str::startsWith($selector, '//')) {
			return $selector;
		}

		if (preg_match('/^([a-z][a-z0-9-]*)$/i', $selector, $match) === 1) {
			return '//' . Str::lower($match[1]);
		}

		if (preg_match('/^#([a-z0-9_-]+)$/i', $selector, $match) === 1) {
			return "//*[@id=\"{$match[1]}\"]";
		}

		if (preg_match('/^\.([a-z0-9_-]+)$/i', $selector, $match) === 1) {
			return "//*[contains(concat(\" \", normalize-space(@class), \" \"), \" {$match[1]} \")]";
		}

		if (preg_match('/^\[([a-z_:][a-z0-9_:.\\-]*)(?:=["\']?([^"\']]+)["\']?)?\]$/i', $selector, $match) === 1) {
			if (isset($match[2]) && $match[2] !== '') {
				return "//*[@{$match[1]}=\"{$match[2]}\"]";
			}

			return "//*[@{$match[1]}]";
		}

		return null;
	}

	protected function rawOption(string $key, mixed $default = null): mixed
	{
		return $this->page->kirby()->option('tobimori.seo.' . $key, $default);
	}
}
