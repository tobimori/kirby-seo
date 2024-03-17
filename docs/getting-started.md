# Getting started

Please make sure to read this page carefully before you start using Kirby SEO. It will most likely answer all your questions.

## How it works - The Meta Cascade 🪜

Kirby SEO is built with a cascading approach in mind. This means that you can define meta tags on multiple levels, and they will be merged together based on their priority:

1. **Page fields**: The default page blueprint that allows you to specify most options (`page`)
2. **Programmatic content**: With Page models you can programmatically set meta tags like you want (`programmatic`)
3. **Inherited fields from parent page**: Most fields can be configured to inherit to child pages (`parent`)
4. **Site globals**: The site blueprint allows you to set global defaults for all pages (`site`)
5. **Options**: The last fallback is defined in the plugin options (`options`)

If any setting is left empty, it will fallback to the next level. In this way, the plugin provides _cascades_ to form the final meta data.

The cascade is fully configurable in your config.php. You can disable any of the above levels, or change their priority, using the ID written in the parenthesis.

There is also another optional cascade level that is not enabled by default:

- **Fallback fields**: Fall back to Meta fields, if the tag is an Open Graph tag (`fallbackFields`)

By default, it is not included, but the fifth level (Options) will have the same behaviour.

## Installation

**Composer is the recommended way to install Kirby SEO.** Run the following command in your terminal:

```
composer require tobimori/kirby-seo
```

Alternatively, you can download and copy this repository to `/site/plugins/kirby-seo`, or apply this repository as Git submodule.

Support will not be provided for manual installations.

## Adding blueprint tabs

Add the blueprint tabs to your blueprints.

```yaml
# site/blueprints/site.yml
tabs:
  content:
    fields:
      # your fields
  seo: seo/site
```

```yaml
# site/blueprints/pages/default.yml (or any other template)
tabs:
  content:
    fields:
      # your fields
  seo: seo/page
```

**The site-level fields are required.** You can add the page-level fields to any template that you want editors to have control over the SEO settings for this page, they're not required for the page to be working with Kirby SEO (it will fall back as the cascade describes).

## Adding meta snippet

Add the meta snippet to your templates:

```php
// site/templates/default.php
<html lang="<?= $site->lang() ?>">
  <head>
    <?php snippet('seo/head'); ?>
  </head>
  <body>
    [...]
    <?php snippet('seo/schemas'); ?>
  </body>
</html>
```

The `seo/head` snippet will output all meta tags, including the `<title>` tag. The `seo/schemas` snippet will output all JSON-LD schemas.

It's important to place the `seo/head` snippet in the `<head>` tag, and the `seo/schemas` snippet at the end of the `<body>` tag.

## Setting your canonical URL

The canonical URL is the URL that you want search engines to index. It's important to set it correctly, otherwise you might get penalized for duplicate content - especially if you run your site both with and without `www`.

You can set the canonical URL in your config file:

```php
// config.php
return [
    'tobimori.seo.canonicalBase' => 'https://www.example.com',
];
```

You can also supply a function that returns the canonical URL. This is helpful for specialized setups that run explicitly on multiple domains.

> While there is a graceful fallback for this to the `$site->url()` method, it's just _safer_ to set it explicitly to prevent any configuration hickups to penalize your site indexing.

## Single-Language Setup

If you're using a single-language setup, it's important to define the language code of your website in the config file:

```php
// config.php
return [
    'tobimori.seo.lang' => 'en_US',
];
```

It's used for the `og:locale` meta tag, and can be applied to the `lang` attribute of your `<html>` tag using the `$site->lang()` site method.

## Heading Structure

The plugin adds a heading structure section to the panel. It dynamically renders your page and allows you to check on the fly if your headings are semantically correct.

You can add the heading structure section to your blueprints:

```yaml
# site/blueprints/pages/default.yml (or any other template)

columns:
  - width: 2/3
    fields:
      # your content-related fields
  - width: 1/3
    sections:
      headings:
        type: heading-structure
```
