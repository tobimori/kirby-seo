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

## All Options

## Options

| Option                    | Default                     | Description                                                               |
| ------------------------- | --------------------------- | ------------------------------------------------------------------------- |
| `robots.active`           | `true`                      | Enable/disable the entire robots module                                   |
| `robots.followPageStatus` | `true`                      | Disable default behavior of blocking drafts/unlisted pages                |
| `robots.pageSettings`     | `true`                      | Show/hide Robots fields in the panel blueprint tab                        |
| `robots.indicator`        | `true`                      | Show/hide indexing indicator next to page status                          |
| `robots.index`            | `fn () => !option('debug')` | Default indexing setting for published pages (can be boolean or callable) |
| `robots.sitemap`          | `null`                      | Sitemap URL to include in `robots.txt`                                    |
| `robots.content`          | `[]`                        | Custom `robots.txt` rules                                                 |
| `robots.types`            | _internal_                  | Valid robot directive types - do not modify                               |
|                           |

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
