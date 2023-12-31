# Robots.txt bot directives

By default, Kirby SEO will dynamically generate a `robots.txt` file and add the `robots` meta tag to any page.

You can disable all Robots handling, by setting `tobimori.seo.robots.active` to `false` in your config.php.

## Panel

![Panel Robots Indicator](/docs/_assets/robots-indicator.png)

Next to the page status button, there is an "Indexing Allowed/Disallowed" button that shows the current robots status of the page.

You can hide this by setting `tobimori.seo.robots.indicator` to `false` in your `config.php`.

![Panel Robots Section](/docs/_assets/robots-section.png)

By default, there is a robots section in the panel. This allows overriding the site-wide robots rules from `config.php` on a per-page basis. The robots fields follow the same cascade logic as other metadata fields.

You can remove this section by setting `tobimori.seo.robots.pageSettings` to `false` in your `config.php`.

## Site-wide Defaults (**Options** cascade)

By default, the page status determines if indexing is allowed. Drafts and unlisted pages are disallowed from indexing. Indexing is also disallowed if debug mode is enabled. These defaults can be overridden in the panel.

You can disable following the page status by setting `tobimori.seo.robots.followPageStatus` to `false` in your config.php.

The default indexing behavior for published pages can be customized by setting `tobimori.seo.robots.index` to a boolean or callable that returns a boolean.

## [Options](/config/options.php)

| Option                    | Default                     | Accepts           | Description                                                                               |
| ------------------------- | --------------------------- | ----------------- | ----------------------------------------------------------------------------------------- |
| `robots.active`           | `true`                      | Boolean           | Enable/disable the entire robots module                                                   |
| `robots.followPageStatus` | `true`                      | Boolean           | Disable default behavior of blocking drafts/unlisted pages                                |
| `robots.pageSettings`     | `true`                      | Boolean           | Show/hide Robots fields in the panel blueprint tab                                        |
| `robots.indicator`        | `true`                      | Boolean           | Show/hide indexing indicator next to page status                                          |
| `robots.index`            | `fn () => !option('debug')` | Boolean / Closure | Default indexing setting for published pages                                              |
| `robots.sitemap`          | `null`                      | String / Closure  | Sitemap URL to include in `robots.txt`, filled automatically if sitemap module is enabled |
| `robots.content`          | `[]`                        | Array             | Custom `robots.txt` rules                                                                 |
| `robots.types`            | _internal_                  | Array             | Valid robot directive types - do not modify                                               |

Options allow you to fine tune the behaviour of the plugin. You can set them in your `config.php` file:

```php
return [
    'tobimori.seo.robots' => [
        'active' => true,
        'content' => [
            '*' => [
                'Allow' => ['/'],
                'Disallow' => ['/kirby', '/panel', '/content']
            ]
        ]
    ],
];
```
