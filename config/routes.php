<?php

use Kirby\Cms\Page;
use Kirby\Http\Response;
use tobimori\Seo\Seo;
use tobimori\Seo\Sitemap\SitemapIndex;

return [
    [
        'pattern' => 'robots.txt',
        'method'  => 'GET|HEAD',
        'action'  => function () {
            if (Seo::option('robots.active')) {
                $content = snippet('seo/robots.txt', [], true);
                return new Response($content, 'text/plain', 200);
            }

            $this->next();
        }
    ],
    [
        'pattern' => 'robots.txt',
        'method'  => 'OPTIONS',
        'action'  => function () {
            return new Response('', 'text/plain', 204, ['Allow' => 'GET, HEAD']);
        }
    ],
    [
        'pattern' => 'robots.txt',
        'method'  => 'ALL',
        'action'  => function () {
            return new Response('Method Not Allowed', 'text/plain', 405, ['Allow' => 'GET, HEAD']);
        }
    ],

    [
        'pattern' => 'sitemap',
        'method'  => 'GET|HEAD',
        'action'  => function () {
            if (!Seo::option('sitemap.redirect') || !Seo::option('sitemap.active')) {
                $this->next();
            }

            go('/sitemap.xml');
        }
    ],
    [
        'pattern' => 'sitemap',
        'method'  => 'OPTIONS',
        'action'  => function () {
            return new Response('', 'text/plain', 204, ['Allow' => 'GET, HEAD']);
        }
    ],
    [
        'pattern' => 'sitemap',
        'method'  => 'ALL',
        'action'  => function () {
            return new Response('Method Not Allowed', 'text/plain', 405, ['Allow' => 'GET, HEAD']);
        }
    ],

    [
        'pattern' => 'sitemap.xsl',
        'method'  => 'GET',
        'action'  => function () {
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
        'method'  => 'OPTIONS',
        'action'  => function () {
            return new Response('', 'text/plain', 204, ['Allow' => 'GET']);
        }
    ],
    [
        'pattern' => 'sitemap.xsl',
        'method'  => 'ALL',
        'action'  => function () {
            return new Response('Method Not Allowed', 'text/plain', 405, ['Allow' => 'GET']);
        }
    ],

    [
        'pattern' => 'sitemap.xml',
        'method'  => 'GET|HEAD',
        'action'  => function () {
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
        'method'  => 'OPTIONS',
        'action'  => function () {
            return new Response('', 'text/plain', 204, ['Allow' => 'GET, HEAD']);
        }
    ],
    [
        'pattern' => 'sitemap.xml',
        'method'  => 'ALL',
        'action'  => function () {
            return new Response('Method Not Allowed', 'text/plain', 405, ['Allow' => 'GET, HEAD']);
        }
    ],

    [
        'pattern' => 'sitemap-(:any).xml',
        'method'  => 'GET|HEAD',
        'action'  => function (string $index) {
            if (!Seo::option('sitemap.active', true)) {
                $this->next();
            }

            SitemapIndex::instance()->generate();
            if (!SitemapIndex::instance()->isValidIndex($index)) {
                $this->next();
            }

            kirby()->response()->type('text/xml');
            return Page::factory([
                'slug' => 'sitemap-' . $index,
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
        'method'  => 'OPTIONS',
        'action'  => function () {
            return new Response('', 'text/plain', 204, ['Allow' => 'GET, HEAD']);
        }
    ],
    [
        'pattern' => 'sitemap-(:any).xml',
        'method'  => 'ALL',
        'action'  => function () {
            return new Response('Method Not Allowed', 'text/plain', 405, ['Allow' => 'GET, HEAD']);
        }
    ],
];
