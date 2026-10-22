---
title: Customizing LLM-friendly Content
intro: Convert HTML pages and customize llms.txt
---

Explicit `.md.php` templates give you full control over Markdown. Automatic conversion is useful when the existing HTML templates already contain well-defined main content. The following setup adds Markdown to those pages without a second set of templates.

## Convert existing pages

Automatic conversion requires both a converter and `agentic.markdown.auto` set to `true`. Installing a converter alone does not enable it.

First, install the default HTML-to-Markdown converter:

```sh
composer require league/html-to-markdown
```

Mark the relevant content in your HTML template. Keep navigation, forms, and other interface elements outside this container:

```php
<main data-agentic-content>
  <h1><?= $page->title() ?></h1>
  <?= $page->text()->kirbytext() ?>
</main>
```

Then enable conversion in `site/config/config.php`:

```php
<?php

return [
  'tobimori.seo' => [
    'agentic' => [
      'markdown' => [
        'auto' => true,
      ],
    ],
  ],
];
```

Open a page with `.md` added to its URL to check the result. For example, `/about` becomes `/about.md`.

Kirby SEO still uses an explicit `.md.php` representation when one exists. Conversion handles only pages without one.

## Use different content selectors

The default selectors are `[data-agentic-content]`, `main`, and `article`. Kirby SEO uses the first matching element. It never converts the complete `<body>`.

Set your own ordered list if the templates use different markup:

```php
'markdown' => [
  'auto' => true,
  'selectors' => ['#content', '.article-content', 'main'],
],
```

Selectors can be element names, IDs, classes, attribute selectors, or XPath expressions. XPath expressions must start with `//`.

## Replace the converter

You do not need `league/html-to-markdown` when you provide a converter. Set `agentic.markdown.converter` to a callable:

```php
'markdown' => [
  'auto' => true,
  'converter' => fn (string $html): string => myMarkdownConverter($html),
],
```

The callable receives the extracted HTML. The [options reference](3_reference/0_options) lists the other accepted converter forms.

## Choose pages for llms.txt

By default, `llms.txt` includes every published, indexable page with Markdown available. Use `agentic.llmsTxt.pages` to filter or reorder the page collection:

```php
use Kirby\Cms\Pages;

'llmsTxt' => [
  'pages' => fn (Pages $pages): Pages => $pages->listed(),
],
```

The normal publication, translation, robots, and Markdown checks still apply after this callback.

## Replace the llms.txt template

Create `site/templates/llms.txt.php` to replace the default output. The template receives `$site`, `$pages`, and `$entries`. Each entry contains its page, title, description, and Markdown URL:

```php
<?php

echo '# ' . $site->title() . "\n\n## Documentation\n";

foreach ($entries as $entry) {
  echo "\n- [{$entry['title']}]({$entry['url']})\n";
}
```

## Staticache

This section applies only if your site uses [Staticache](https://github.com/getkirby/staticache). Staticache is a Kirby plugin that makes a site faster: it saves each page as a file, and your web server then sends this file without starting Kirby.

### The problem

Kirby SEO gives each page a Markdown version for AI agents, for example `/about.md` for `/about`. With this Markdown version, Kirby SEO sends HTTP headers that tell search engines and agents how to use it. The two most important ones are:

- `X-Robots-Tag: noindex` tells search engines not to show `/about.md` in search results.
- `Link` tells them that `/about` is the original page, and tells agents where to find your `llms.txt`.

Staticache also saves the Markdown version as a file. By default, it saves only the text of the file and not the headers. So only the first request to `/about.md` gets the headers, because Kirby still answers it. All later requests get the saved file without headers, and search engines can show `/about.md` as a copy of `/about`.

Kirby's own pages cache does not have this problem.

### The solution

Your web server must send the headers with the saved file. How you do this depends on the web server:

- **Apache (with `mod_asis`) and the Staticache PHP loader** can read headers from the saved file. Enable the Staticache `headers` option.
- **nginx and Caddy** cannot do this. Add the headers in the server configuration.

After you change the configuration, delete the Staticache cache directory. Staticache then saves the pages again.

Agents can also ask for Markdown on the normal URL, with the `Accept: text/markdown` header. This does not work with Staticache, because the web server sends the saved HTML file before Kirby can answer. Agents must use the `.md` URL.

### Apache and the PHP loader

Enable the `headers` option. Staticache then writes the headers into the cached file:

```php
<?php
// site/config/config.php

return [
  'cache' => [
    'pages' => [
      'active' => true,
      'type' => 'static',
      'headers' => true,
    ],
  ],
];
```

Then set up your server as shown in the [Staticache documentation](https://github.com/getkirby/staticache#header-support): `mod_asis` for Apache, or the PHP loader with header support.

### nginx and Caddy

Do not enable the `headers` option, because these servers send the header lines as part of the body.

The examples use the default Staticache path `site/cache/<host>/pages`. Change it if you use a custom `root` or `prefix`. Keep your existing Staticache rules for HTML pages.

#### nginx

Put the `map` block in the `http` context, outside the `server` block:

```nginx
map $seo_markdown $seo_canonical {
  ~^/home\.md$  "";
  ~^(.*)\.md$   $1;
}

server {
  # ...

  location ~ ^(?<seo_markdown>.+\.md)$ {
    default_type text/markdown;
    charset utf-8;
    charset_types text/markdown;

    add_header Link '<$scheme://$http_host$seo_canonical>; rel="canonical", <$scheme://$http_host/llms.txt>; rel="describedby"';
    add_header Content-Location $scheme://$http_host$seo_markdown;
    add_header Vary Accept;
    add_header X-Content-Type-Options nosniff;
    add_header X-Robots-Tag noindex;

    try_files /site/cache/$host/pages$uri /index.php$is_args$args;
  }
}
```

`add_header` in a `location` block removes all `add_header` lines from the `server` block, such as security headers. Repeat them in this block.

On a multi-language site, replace the `map` block. List the URL prefix of each language, and set `default` to the code of the language without a prefix:

```nginx
map $seo_markdown $seo_canonical {
  ~^(/de|/fr)?/home\.md$  $1;
  ~^(.*)\.md$             $1;
}
map $seo_markdown $seo_prefix {
  ~^(/de|/fr)/  $1;
  default       "";
}
map $seo_markdown $seo_language {
  ~^/(de|fr)/  $1;
  default      en;
}
```

Then replace the `Link` header and add `Content-Language`:

```nginx
add_header Link '<$scheme://$http_host$seo_canonical>; rel="canonical", <$scheme://$http_host$seo_prefix/llms.txt>; rel="describedby"';
add_header Content-Language $seo_language;
```

#### Caddy

```text
example.com {
  root * /path/to/your/site

  map {path} {seo_canonical} {
    ~^/home\.md$  ""
    ~^(.*)\.md$   "${1}"
  }

  @markdown {
    path *.md
    file /site/cache/{host}/pages{path}
  }
  header @markdown {
    Content-Type "text/markdown; charset=utf-8"
    Link "<{scheme}://{hostport}{seo_canonical}>; rel=\"canonical\", <{scheme}://{hostport}/llms.txt>; rel=\"describedby\""
    Content-Location "{scheme}://{hostport}{path}"
    Vary Accept
    X-Content-Type-Options nosniff
    X-Robots-Tag noindex
  }

  file_server
  php_fastcgi unix//var/run/php-fpm.sock {
    try_files {path} site/cache/{host}/pages{path}/index.html site/cache/{host}/pages{path} index.php
  }
}
```

On a multi-language site, replace the `map` block. List the URL prefix of each language, and use the code of the language without a prefix in the last two rules:

```text
map {path} {seo_canonical} {seo_prefix} {seo_language} {
  ~^/(de|fr)/home\.md$  /${1}       /${1}  ${1}
  ~^/(de|fr)/(.*)\.md$  /${1}/${2}  /${1}  ${1}
  ~^/home\.md$          ""          ""     en
  ~^(.*)\.md$           ${1}        ""     en
}
```

Then replace the `Link` header and add `Content-Language`:

```text
Link "<{scheme}://{hostport}{seo_canonical}>; rel=\"canonical\", <{scheme}://{hostport}{seo_prefix}/llms.txt>; rel=\"describedby\""
Content-Language {seo_language}
```

#### Limits

The server builds the headers from the URL, so it does not know your Kirby settings:

- The canonical URL is wrong for pages with a custom canonical URL, or when you use the `canonical.base` or `canonical.trailingSlash` options. Exclude these pages with the Staticache `ignore` option.
- If you change Kirby's `home` option, replace `home` in the rules.
- When you add a language, add its URL prefix to the rules.

Cached HTML pages also lose the `Link` header to their Markdown version. The `<link rel="alternate" type="text/markdown">` tag in the page head still points to it.
