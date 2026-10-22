---
title: Sitemap
intro: A sitemap for search engines, generated from your pages
---

Kirby SEO generates an XML sitemap at `https://example.com/sitemap.xml`. Search engines like Google use it to discover all the pages on your site. You don't need to create or update it manually: it updates whenever your content changes.

What you see here are the defaults. The sitemap generator and all its options can be changed or replaced entirely. See [Customizing the sitemap](2_customization/04_sitemap) for details.

## What's in the sitemap

The sitemap includes each page or translation whose resolved [robots indexing setting](1_features/00_robots-indexing) allows indexing. Page status provides the default. Higher-priority values in the Meta Cascade can override it. Custom `robots.txt` rules from `robots.content` do not affect the sitemap. The `error` template is also excluded by default.

Each page in the sitemap includes:

- `loc`: the page URL
- `lastmod`: when the page was last modified
- `changefreq`: how often the page is likely to change (default: `weekly`)
- `priority`: how important the page is relative to other pages on your site

Priority is calculated from page depth: the homepage gets `1.0`, and each level deeper subtracts `0.2`, down to a minimum of `0.2`.

A `Sitemap:` line is also added to your [robots.txt](1_features/00_robots-indexing) automatically, so crawlers know where to find it.

## Multilingual sites

If your Kirby site has multiple languages, the sitemap automatically includes `hreflang` links for each page. These tell search engines which language versions of a page exist, so they can show the right one in search results.

Each translation gets its own entry with its translated URL, and every entry lists the same `<xhtml:link>` alternates, including itself and an `x-default` fallback. Only translations that exist and are indexable are included. There is no separate sitemap per language: all translations are listed in a single sitemap.

## Browser view

If you open `https://example.com/sitemap.xml` in a browser, you'll see a styled table instead of raw XML. This is powered by an XSL stylesheet that Kirby SEO serves at `/sitemap.xsl`. On multilingual sites, each URL shows language badges linking to its alternate translations.

To see the raw XML, use `view-source:https://example.com/sitemap.xml` in your browser's address bar.
