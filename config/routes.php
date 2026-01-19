<?php

use Kirby\Cms\App;
use Kirby\Cms\Page;
use Kirby\Http\Response;
use Kirby\Data\Json;
use tobimori\Seo\Seo;
use tobimori\Seo\Sitemap\SitemapIndex;

return [
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
	[
		'pattern' => 'robots.txt',
		'method' => 'OPTIONS',
		'action' => function () {
			if (Seo::option('robots.active')) {
				return new Response('', 'text/plain', 204, ['Allow' => 'GET, HEAD']);
			}

			$this->next();
		}
	],
	[
		'pattern' => 'robots.txt',
		'method' => 'ALL',
		'action' => function () {
			if (Seo::option('robots.active')) {
				return new Response('Method Not Allowed', 'text/plain', 405, ['Allow' => 'GET, HEAD']);
			}

			$this->next();
		}
	],

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
	[
		'pattern' => 'sitemap',
		'method' => 'OPTIONS',
		'action' => function () {
			if (Seo::option('sitemap.active')) {
				return new Response('', 'text/plain', 204, ['Allow' => 'GET, HEAD']);
			}

			$this->next();
		}
	],
	[
		'pattern' => 'sitemap',
		'method' => 'ALL',
		'action' => function () {
			if (Seo::option('sitemap.active')) {
				return new Response('Method Not Allowed', 'text/plain', 405, ['Allow' => 'GET, HEAD']);
			}

			$this->next();
		}
	],

	[
		'pattern' => 'sitemap.xsl',
		'method' => 'GET',
		'action' => function () {
			if (!Seo::option('sitemap.active')) {
				$this->next();
			}

			kirby()->response()->type('text/xsl');

			$lang = Seo::option('sitemap.locale', 'en');
			kirby()->setCurrentTranslation($lang);

			return Page::factory([
				'slug' => 'sitemap',
				'template' => 'sitemap',
				'model' => 'sitemap',
				'content' => [
					'title' => t('sitemap'),
				],
			])->render(contentType: 'xsl');
		}
	],
	[
		'pattern' => 'sitemap.xsl',
		'method' => 'OPTIONS',
		'action' => function () {
			if (Seo::option('sitemap.active')) {
				return new Response('', 'text/plain', 204, ['Allow' => 'GET']);
			}

			$this->next();
		}
	],
	[
		'pattern' => 'sitemap.xsl',
		'method' => 'ALL',
		'action' => function () {
			if (Seo::option('sitemap.active')) {
				return new Response('Method Not Allowed', 'text/plain', 405, ['Allow' => 'GET']);
			}

			$this->next();
		}
	],

	[
		'pattern' => 'sitemap.xml',
		'method' => 'GET|HEAD',
		'action' => function () {
			if (!Seo::option('sitemap.active', true)) {
				$this->next();
			}

			SitemapIndex::instance()->generate();
			kirby()->response()->type('text/xml');
			return Page::factory([
				'slug' => 'sitemap',
				'template' => 'sitemap',
				'model' => 'sitemap',
				'content' => [
					'title' => t('sitemap'),
					'index' => null,
				],
			])->render(contentType: 'xml');
		}
	],
	[
		'pattern' => 'sitemap.xml',
		'method' => 'OPTIONS',
		'action' => function () {
			if (Seo::option('sitemap.active', true)) {
				return new Response('', 'text/plain', 204, ['Allow' => 'GET, HEAD']);
			}

			$this->next();
		}
	],
	[
		'pattern' => 'sitemap.xml',
		'method' => 'ALL',
		'action' => function () {
			if (Seo::option('sitemap.active', true)) {
				return new Response('Method Not Allowed', 'text/plain', 405, ['Allow' => 'GET, HEAD']);
			}

			$this->next();
		}
	],

	[
		'pattern' => 'sitemap-(:any).xml',
		'method' => 'GET|HEAD',
		'action' => function (string $index) {
			if (!Seo::option('sitemap.active', true)) {
				$this->next();
			}

			SitemapIndex::instance()->generate();
			if (!SitemapIndex::instance()->isValidIndex($index)) {
				$this->next();
			}

			kirby()->response()->type('text/xml');
			return Page::factory([
				'slug' => "sitemap-{$index}",
				'template' => 'sitemap',
				'model' => 'sitemap',
				'content' => [
					'title' => t('sitemap'),
					'index' => $index,
				],
			])->render(contentType: 'xml');
		}
	],
	[
		'pattern' => 'sitemap-(:any).xml',
		'method' => 'OPTIONS',
		'action' => function () {
			if (Seo::option('sitemap.active')) {
				return new Response('', 'text/plain', 204, ['Allow' => 'GET, HEAD']);
			}

			$this->next();
		}
	],
	[
		'pattern' => 'sitemap-(:any).xml',
		'method' => 'ALL',
		'action' => function () {
			if (Seo::option('sitemap.active')) {
				return new Response('Method Not Allowed', 'text/plain', 405, ['Allow' => 'GET, HEAD']);
			}

			$this->next();
		}
	],

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
