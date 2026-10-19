<?php

use Kirby\Cms\App;
use Kirby\Cms\Page;
use Kirby\Exception\NotFoundException;
use Kirby\Http\Response;
use Kirby\Http\Route;
use Kirby\Toolkit\I18n;
use Kirby\Toolkit\Str;
use tobimori\Seo\Seo;

/**
 * Answers OPTIONS and rejects other methods for an endpoint,
 * if the option that enables the endpoint is active
 */
$methodRoutes = /** @param 'robots.active'|'sitemap.active' $option */ fn (string $pattern, string $allow, string $option) => [
	[
		'pattern' => $pattern,
		'method' => 'OPTIONS',
		'action' => function () use ($allow, $option) {
			/** @var Route $this */
			if (Seo::option($option)) {
				return new Response('', 'text/plain', 204, ['Allow' => $allow]);
			}

			$this->next();
		}
	],
	[
		'pattern' => $pattern,
		'method' => 'ALL',
		'action' => function () use ($allow, $option) {
			/** @var Route $this */
			// allowed methods only get here if their route passed on (e.g. unknown sitemap index), which is not a method error
			if (Seo::option($option) && !in_array(App::instance()->request()->method(), explode(', ', $allow), true)) {
				return new Response('Method Not Allowed', 'text/plain', 405, ['Allow' => $allow]);
			}

			$this->next();
		}
	]
];

$sitemapPage = fn (string|null $index = null) => Page::factory([
	'slug' => $index ? "sitemap-{$index}" : 'sitemap',
	'template' => 'sitemap',
	'model' => 'sitemap',
	'content' => [
		'title' => I18n::translate('seo.sitemap.title'),
		'index' => $index
	]
]);

return [
	[
		'pattern' => 'llms.txt',
		'method' => 'GET|HEAD',
		'language' => '*',
		'action' => function () {
			/** @var Route $this */
			// the language router sets the current language
			$page = App::instance()->site()->homePage();
			if ($page === null) {
				return $this->next();
			}

			$llmContent = new (Seo::option('components.agentic'))($page);
			if ($llmContent->llmsTxtEnabled() === false) {
				$this->next();
			}

			// the page list is built on a cache miss and rejected there if it is empty
			try {
				return $llmContent->llmsTxtPage()->render(contentType: 'txt');
			} catch (NotFoundException) {
				$this->next();
			}
		}
	],
	[
		'pattern' => 'indexnow-(:any).txt',
		'method' => 'GET',
		'action' => function (string $key) {
			/** @var Route $this */
			if (Seo::option('indexnow.enabled') && Seo::option('components.indexnow')::verifyKey($key)) {
				return new Response($key, 'text/plain', 200);
			}

			$this->next();
		}
	],

	[
		'pattern' => 'robots.txt',
		'method' => 'GET|HEAD',
		'action' => function () {
			/** @var Route $this */
			if (Seo::option('robots.active')) {
				$content = App::instance()->snippet('seo/robots.txt', [], true);
				return new Response($content, 'text/plain', 200);
			}

			$this->next();
		}
	],
	...$methodRoutes('robots.txt', 'GET, HEAD', 'robots.active'),

	[
		'pattern' => 'sitemap',
		'method' => 'GET|HEAD',
		'action' => function () {
			/** @var Route $this */
			if (!Seo::option('sitemap.redirect') || !Seo::option('sitemap.active')) {
				$this->next();
			}

			Response::go('/sitemap.xml');
		}
	],
	...$methodRoutes('sitemap', 'GET, HEAD', 'sitemap.active'),

	[
		'pattern' => 'sitemap.xsl',
		'method' => 'GET',
		'action' => function () use ($sitemapPage) {
			/** @var Route $this */
			if (!Seo::option('sitemap.active')) {
				$this->next();
			}

			App::instance()->response()->type('text/xsl');
			App::instance()->setCurrentTranslation(Seo::option('sitemap.locale', 'en'));

			return $sitemapPage()->render(contentType: 'xsl');
		}
	],
	...$methodRoutes('sitemap.xsl', 'GET', 'sitemap.active'),

	[
		'pattern' => 'sitemap.xml',
		'method' => 'GET|HEAD',
		'action' => function () use ($sitemapPage) {
			/** @var Route $this */
			if (!Seo::option('sitemap.active')) {
				$this->next();
			}

			return $sitemapPage()->render(contentType: 'xml');
		}
	],
	...$methodRoutes('sitemap.xml', 'GET, HEAD', 'sitemap.active'),

	[
		'pattern' => 'sitemap-(:any).xml',
		'method' => 'GET|HEAD',
		'action' => function (string $index) use ($sitemapPage) {
			/** @var Route $this */
			if (!Seo::option('sitemap.active')) {
				$this->next();
			}

			// the index is generated lazily on a cache miss and rejected there if invalid
			try {
				return $sitemapPage($index)->render(contentType: 'xml');
			} catch (NotFoundException) {
				$this->next();
			}
		}
	],
	...$methodRoutes('sitemap-(:any).xml', 'GET, HEAD', 'sitemap.active'),

	// Google Search Console OAuth
	[
		'pattern' => '__seo/gsc/auth',
		'method' => 'GET',
		'action' => function () {
			$kirby = App::instance();
			if (!$kirby->user() || !Seo::option('searchConsole.enabled') || !Seo::option('components.gsc')::hasCredentials()) {
				Response::go($kirby->site()->panel()->url());
			}

			$panel = $kirby->url('panel');
			$return = $kirby->request()->get('return');
			if ($panel === null || !is_string($return) || $return !== $panel && !Str::startsWith($return, $panel . '/')) {
				$return = $kirby->site()->panel()->url();
			}

			$state = bin2hex(random_bytes(16));
			$kirby->session()->data()->set('tobimori.seo.gsc', ['state' => $state, 'return' => $return]);

			$redirectUri = rtrim((string)$kirby->url(), '/') . '/__seo/gsc/callback';
			Response::go(Seo::option('components.gsc')::authUrl($redirectUri, $state));
		}
	],
	[
		'pattern' => '__seo/gsc/callback',
		'method' => 'GET',
		'action' => function () {
			$kirby = App::instance();
			if (!$kirby->user()) {
				Response::go($kirby->site()->panel()->url());
			}

			$request = $kirby->request();
			$session = $kirby->session()->data()->pull('tobimori.seo.gsc');
			$state = $request->get('state');
			if (!is_array($session) || !is_string($session['state'] ?? null) || !is_string($state) || !hash_equals($session['state'], $state)) {
				throw new \Exception('Invalid OAuth state');
			}

			if ($error = $request->get('error')) {
				throw new \Exception("OAuth error: {$error}");
			}

			$code = $request->get('code');
			if (!is_string($code) || $code === '') {
				throw new \Exception('No authorization code received');
			}

			$redirectUri = rtrim((string)$kirby->url(), '/') . '/__seo/gsc/callback';
			Seo::option('components.gsc')::exchangeCode($code, $redirectUri);

			// redirect back to where the user came from
			Response::go((string)$session['return']);
		}
	]
];
