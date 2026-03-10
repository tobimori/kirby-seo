---
title: Options
intro: All configuration options
---

All options are set under `tobimori.seo` in your `config.php`. Dots in the option names represent nested arrays. For example, `robots.enabled` becomes:

```php
<?php
// site/config/config.php

return [
  'tobimori.seo' => [
    'robots' => [
      'enabled' => true,
    ],
  ],
];
```

You can also use Kirby's flat dot syntax:

```php
return [
  'tobimori.seo.robots.enabled' => true,
];
```

Both are equivalent, but you cannot use dot syntax inside a nested array. `'robots.enabled' => true` only works at the top level as `'tobimori.seo.robots.enabled'`. Inside the `'tobimori.seo'` array, you must use nested arrays.

## General

| Option                    | Default                                                   | Description                                                                                                     |
| ------------------------- | --------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------- |
| `locale`                  | `'en_US'`                                                 | Default locale for single-language sites                                                                        |
| `dateFormat`              | `null`                                                    | Custom date format for dates in meta tags                                                                       |
| `generateSchema`          | `true`                                                    | Generate a default `WebSite` schema for every page. Requires [spatie/schema-org](2_customization/08_schema-org) |
| `previews`                | `['google', 'facebook', 'slack']`                         | Which preview types to show in the Panel                                                                        |
| `cascade`                 | `['fields', 'programmatic', 'parent', 'fallbackFields', 'site', 'options']` | The [meta cascade](2_customization/01_meta-cascade) order                                                       |
| `canonical.base`          | `null`                                                    | Base URL for canonical links. Uses the site URL if not set                                                      |
| `canonical.trailingSlash` | `false`                                                   | Add trailing slashes to canonical URLs                                                                          |
| `files.parent`            | `null`                                                    | Default parent page for file uploads in SEO fields                                                              |
| `files.template`          | `null`                                                    | Default file template for SEO file uploads                                                                      |
| `socialMedia`             | See below                                                 | Social media account fields shown in the site blueprint                                                         |

The `socialMedia` option defines which fields appear in the site blueprint. Default fields: `twitter`, `facebook`, `instagram`, `youtube`, `linkedin`, `bluesky`, `mastodon`. Each key maps to a placeholder URL. Override the array to add or remove fields.

## Meta defaults

These are the fallback values for the last level of the [meta cascade](2_customization/01_meta-cascade). They apply when no other level provides a value. Each option can be a static value or a callable that receives the `Page` object.

| Option                     | Default                            | Description                                                |
| -------------------------- | ---------------------------------- | ---------------------------------------------------------- |
| `default.metaTitle`        | Page title                         | Meta title                                                 |
| `default.metaTemplate`     | `'{{ title }} - {{ site.title }}'` | Title template applied to all pages                        |
| `default.ogTemplate`       | `'{{ title }}'`                    | Open Graph title template                                  |
| `default.ogSiteName`       | Site title                         | Open Graph site name                                       |
| `default.ogType`           | `'website'`                        | Open Graph type                                            |
| `default.ogDescription`    | Meta description                   | Open Graph description, falls back to the meta description |
| `default.cropOgImage`      | `true`                             | Crop OG images to 1200x630                                 |
| `default.locale`           | Language locale or `'en_US'`       | Locale for meta tags                                       |
| `default.robotsIndex`      | `true` if listed and not debug     | Whether pages are indexable                                |
| `default.robotsFollow`     | Same as `robotsIndex`              | Whether links are followed                                 |
| `default.robotsArchive`    | Same as `robotsIndex`              | Whether archiving is allowed                               |
| `default.robotsImageindex` | Same as `robotsIndex`              | Whether image indexing is allowed                          |
| `default.robotsSnippet`    | Same as `robotsIndex`              | Whether snippets are allowed                               |

## Robots

| Option                    | Default                                                   | Description                                                                                                            |
| ------------------------- | --------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------- |
| `robots.enabled`          | `true`                                                    | Whether the plugin handles robots meta tags and robots.txt                                                             |
| `robots.index`            | `true` (unless debug mode)                                | Site-wide indexing default. Set to `false` to noindex the entire site                                                  |
| `robots.followPageStatus` | `true`                                                    | Unlisted pages are noindex by default                                                                                  |
| `robots.pageSettings`     | `true`                                                    | Show robots settings on each page in the Panel                                                                         |
| `robots.types`            | `['index', 'follow', 'archive', 'imageindex', 'snippet']` | Available robot directive types. Add `'ai'` and `'imageai'` for [AI training controls](2_customization/03_robots-noai) |
| `robots.content`          | `[]`                                                      | Custom [robots.txt rules](2_customization/02_robots-txt) per user agent                                                |
| `robots.sitemap`          | `null`                                                    | Custom sitemap URL for robots.txt. Auto-detected when the sitemap module is active                                     |

## Sitemap

| Option                     | Default               | Description                                                                                                   |
| -------------------------- | --------------------- | ------------------------------------------------------------------------------------------------------------- |
| `sitemap.enabled`          | `true`                | Whether to generate a sitemap                                                                                 |
| `sitemap.redirect`         | `true`                | Redirect `/sitemap` to `/sitemap.xml`                                                                         |
| `sitemap.locale`           | `'en'`                | Locale for the sitemap XSL stylesheet                                                                         |
| `sitemap.generator`        | Built-in generator    | A callable that receives a `SitemapIndex` instance. See [customizing the sitemap](2_customization/05_sitemap) |
| `sitemap.changefreq`       | `'weekly'`            | Default change frequency. Static value or callable                                                            |
| `sitemap.priority`         | Calculated from depth | Homepage gets `1.0`, each level deeper subtracts `0.2`, minimum `0.2`                                         |
| `sitemap.groupByTemplate`  | `false`               | Split the sitemap into separate files per template                                                            |
| `sitemap.excludeTemplates` | `['error']`           | Templates to exclude from the sitemap                                                                         |

## AI Assist

| Option         | Default    | Description                       |
| -------------- | ---------- | --------------------------------- |
| `ai.enabled`   | `true`     | Whether AI features are available |
| `ai.provider`  | `'openai'` | The active provider ID            |
| `ai.providers` | See below  | Provider configurations           |

Each provider has a `driver` class and a `config` array. The config options depend on the driver. All built-in drivers share `apiKey` (required), `model`, `endpoint`, and `timeout`. The OpenAI driver also accepts `organization`.

| Provider     | Driver      | Default model                    | Default endpoint                                          |
| ------------ | ----------- | -------------------------------- | --------------------------------------------------------- |
| `openai`     | `OpenAi`    | `gpt-5-mini-2025-08-07`         | `https://api.openai.com/v1/responses`                     |
| `anthropic`  | `Anthropic` | `claude-4-5-haiku`               | `https://api.anthropic.com/v1/messages`                   |
| `gemini`     | `Gemini`    | `gemini-3.1-flash-lite-preview`  | `https://generativelanguage.googleapis.com/v1beta`        |
| `openrouter` | `OpenAi`    | `openai/gpt-5-nano`             | `https://openrouter.ai/api/v1/responses`                  |

The Gemini driver authenticates via API key as a query parameter (not a header). All providers default to a timeout of 120 seconds. See [customizing AI Assist](2_customization/06_ai-assist) for adding your own provider.

## IndexNow

| Option                  | Default                      | Description                                                |
| ----------------------- | ---------------------------- | ---------------------------------------------------------- |
| `indexnow.enabled`      | `true`                       | Whether to ping search engines on page changes             |
| `indexnow.searchEngine` | `'https://api.indexnow.org'` | IndexNow API endpoint. One engine propagates to all others |
| `indexnow.rules`        | `[]`                         | Invalidation rules for related pages                       |

Rules map a match pattern to invalidation targets. Match patterns can be a URL glob (`'/blog/*'`), a template name (`'article'`), or a wildcard (`'*'`).

| Target      | Value           | Description                                                |
| ----------- | --------------- | ---------------------------------------------------------- |
| `parent`    | `true` or `int` | Invalidate the direct parent (`true`) or N levels up       |
| `children`  | `true` or `int` | Invalidate all descendants (`true`) or up to N levels deep |
| `siblings`  | `true`          | Invalidate all siblings at the same level                  |
| `urls`      | `string[]`      | Specific URLs to invalidate                                |
| `templates` | `string[]`      | Invalidate all pages with these templates                  |

```php
'indexnow' => [
  'rules' => [
    'article' => ['parent' => true, 'urls' => ['/blog', '/']],
    'product' => ['parent' => true, 'siblings' => true, 'templates' => ['category']],
  ],
],
```

## Search Console

| Option                      | Default                        | Description                                                                   |
| --------------------------- | ------------------------------ | ----------------------------------------------------------------------------- |
| `searchConsole.enabled`     | `true`                         | Whether the Search Console integration is active                              |
| `searchConsole.credentials` | `null`                         | Google OAuth credentials array. See [GSC setup](2_customization/07_gsc-setup) |
| `searchConsole.tokenPath`   | `site/config/.gsc-tokens.json` | Where OAuth tokens are stored                                                 |

## Components

| Option                | Default                            | Description               |
| --------------------- | ---------------------------------- | ------------------------- |
| `components.meta`     | `tobimori\Seo\Meta`                | Meta tag generation class |
| `components.ai`       | `tobimori\Seo\Ai`                  | AI Assist class           |
| `components.indexnow` | `tobimori\Seo\IndexNow`            | IndexNow class            |
| `components.schema`   | `tobimori\Seo\SchemaSingleton`     | Schema.org store class    |
| `components.gsc`      | `tobimori\Seo\GoogleSearchConsole` | Search Console class      |

See [extending the plugin](2_customization/11_plugin-extensions) for details on replacing components.
