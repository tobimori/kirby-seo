<?php

use Kirby\Cms\App;
use Kirby\Cms\Page;
use Kirby\Http\Response;
use Kirby\Data\Json;
use Kirby\Exception\NotFoundException;
use tobimori\Seo\Seo;

/**
 * Answers OPTIONS and rejects other methods for an endpoint,
 * if the option that enables the endpoint is active
 */
$methodRoutes = fn (string $pattern, string $allow, string $option) => [
	[
		'pattern' => $pattern,
		'method' => 'OPTIONS',
		'action' => function () use ($allow, $option) {
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
			// allowed methods only get here if their route passed on (e.g. unknown sitemap index), which is not a method error
			if (Seo::option($option) && !in_array(App::instance()->request()->method(), explode(', ', $allow), true)) {
				return new Response('Method Not Allowed', 'text/plain', 405, ['Allow' => $allow]);
			}

			$this->next();
		}
	],
];

$sitemapPage = fn (string|null $index = null) => Page::factory([
	'slug' => $index ? "sitemap-{$index}" : 'sitemap',
	'template' => 'sitemap',
	'model' => 'sitemap',
	'content' => [
		'title' => t('seo.sitemap.title'),
		'index' => $index,
	],
]);

return [
	[
		'pattern' => 'llms.txt',
		'method' => 'GET|HEAD',
		'language' => '*',
		'action' => function () {
			// the language router sets the current language
			$page = App::instance()->site()->homePage();
			if ($page === null) {
				$this->next();
			}

			$llmContent = new (Seo::option('components.agentic'))($page);
			if ($llmContent->llmsTxtEnabled() === false) {
				$this->next();
			}

			return $llmContent->llmsTxtPage()->render(contentType: 'txt');
		}
	],
	[
		'pattern' => 'indexnow-(:any).txt',
		'method' => 'GET',
		'action' => function (string $key) {
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
			if (Seo::option('robots.active')) {
				$content = snippet('seo/robots.txt', [], true);
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
			if (!Seo::option('sitemap.redirect') || !Seo::option('sitemap.active')) {
				$this->next();
			}

			go('/sitemap.xml');
		}
	],
	...$methodRoutes('sitemap', 'GET, HEAD', 'sitemap.active'),

	[
		'pattern' => 'sitemap.xsl',
		'method' => 'GET',
		'action' => function () use ($sitemapPage) {
			if (!Seo::option('sitemap.active')) {
				$this->next();
			}

			kirby()->response()->type('text/xsl');
			kirby()->setCurrentTranslation(Seo::option('sitemap.locale', 'en'));

			return $sitemapPage()->render(contentType: 'xsl');
		}
	],
	...$methodRoutes('sitemap.xsl', 'GET', 'sitemap.active'),

	[
		'pattern' => 'sitemap.xml',
		'method' => 'GET|HEAD',
		'action' => function () use ($sitemapPage) {
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
				go($kirby->site()->panel()->url());
			}

			$return = $kirby->request()->get('return') ?? $kirby->site()->panel()->url();
			$state = base64_encode(Json::encode([
				'csrf' => bin2hex(random_bytes(16)),
				'return' => $return
			]));

			$redirectUri = rtrim($kirby->url(), '/') . '/__seo/gsc/callback';
			go(Seo::option('components.gsc')::authUrl($redirectUri, $state));
		}
	],
	[
		'pattern' => '__seo/gsc/callback',
		'method' => 'GET',
		'action' => function () {
			$kirby = App::instance();
			if (!$kirby->user()) {
				go($kirby->site()->panel()->url());
			}

			$request = $kirby->request();
			$state = Json::decode(base64_decode($request->get('state')));
			if (!$state || empty($state['csrf'])) {
				throw new \Exception('Invalid OAuth state');
			}

			if ($error = $request->get('error')) {
				throw new \Exception("OAuth error: {$error}");
			}

			if (!($code = $request->get('code'))) {
				throw new \Exception('No authorization code received');
			}

			$redirectUri = rtrim($kirby->url(), '/') . '/__seo/gsc/callback';
			Seo::option('components.gsc')::exchangeCode($code, $redirectUri);

			// redirect back to where the user came from
			$return = $state['return'] ?? $kirby->site()->panel()->url();
			go($return);
		}
	],
];
