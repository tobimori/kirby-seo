---
title: LLM-friendly Content
intro: Publish an llms.txt index and clean Markdown versions of pages
---

Web pages are designed for people using browsers. Their HTML contains the page content, but also navigation, layout, forms, cookie notices, and other interface elements. AI assistants and automated research tools can parse this HTML, but they first have to separate the content from the surrounding interface.

Kirby SEO gives these clients a text-focused version of the same page. It does not replace the website or create a second source of content: the HTML page remains the canonical version for people and search engines, while Markdown provides a simpler representation for clients that request it. `llms.txt` gives those clients one place to discover the available representations.

## Providing Markdown

Each page needs a Markdown source before Kirby SEO can publish or list it. There are two ways to provide one:

- Create a Kirby `.md.php` content representation when you want full control over the result.
- Enable automatic conversion when the existing HTML already contains a clear main content area.

Kirby SEO uses an explicit representation first. Automatic conversion is only a fallback for pages without one.

A Kirby content representation uses the page's normal template name with `.md.php` added:

```text
site/templates/article.php
site/templates/article.md.php
```

The Markdown template receives the same `$page` object as the HTML template:

```php
<?php
// site/templates/article.md.php
?>
# <?= $page->title() ?>

<?= $page->text()->value() ?>
```

`site/templates/default.md.php` can provide a shared representation for several page types. A template such as `article.md.php` takes priority for articles.

Automatic conversion avoids separate templates. It extracts the main content from the rendered HTML and converts that fragment to Markdown. It requires an HTML-to-Markdown converter and must be enabled in the plugin options. See [Customizing LLM-friendly Content](2_customization/12_llm-content) for the setup.

Without a `.md.php` template or automatic conversion, a page has no Markdown representation and does not appear in `llms.txt`.

## Requesting Markdown

Clients can use the `.md` URL directly:

```text
https://example.com/article.md
```

They can also request Markdown from the normal page URL:

```http
GET /article
Accept: text/markdown
```

Kirby SEO returns Markdown only when the request prefers it over HTML. Browser requests continue to receive the normal page. Links between local pages use `.md` when the target page has a Markdown representation. File, anchor, query-only, route, and external links remain unchanged.

The HTML response also advertises its Markdown alternative. The Markdown response points back to the canonical HTML page and to the site's `llms.txt` file.

## Discovering pages with llms.txt

The [llms.txt proposal](https://llmstxt.org/) defines a concise Markdown file that introduces a site and links to more detailed Markdown documents. A client can read this entry point before it decides which pages to fetch.

Kirby SEO publishes the file at `/llms.txt`:

```markdown
# Example Site

> Articles and guides from Example Site.

## Pages

- [About](https://example.com/about.md)
- [First article](https://example.com/blog/first-article.md)
```

The list contains published, indexable pages that have Markdown available. On multilingual sites, each language route lists the pages translated into that language.
