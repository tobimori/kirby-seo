---
title: Quickstart
intro: "All you need to get started with Kirby SEO: Installation & initial configuration"
---

## Requirements

Kirby SEO requires

- Kirby 5 or later
- PHP 8.3, 8.4 or 8.5

Composer is required for full feature support (e.g. schema.org support, background queuing). [Composer](https://getcomposer.org/) is a dependency manager for PHP. If you have never used Composer before, follow the instruction on the [Composer website](https://getcomposer.org/doc/00-intro.md).

## Installing Kirby SEO

In a terminal window, navigate to the folder of your Kirby installation. Then run the following command:

```bash
composer require tobimori/kirby-seo
```

Some features require additional packages. Install them when you need them:

- [Schema.org](2_customization/08_schema-org) requires `spatie/schema-org`
- Background Processing (coming soon)

<details>
<summary>Manual Installation</summary>

If you prefer not to use Composer, you can manually install Kirby SEO. Go to the [GitHub releases page](https://github.com/tobimori/kirby-seo/releases) and find the latest release. Click on "Assets" to expand it and select "Source code (zip)". Extract the contents of the zip file into the `site/plugins/kirby-seo` folder of your Kirby installation.

</details>

## Add meta tags to your site

Kirby SEO needs two snippets in your HTML: one in the `<head>` for meta tags, and one before `</body>` for structured data.

Find the place in your code where you output the `<head>` tag, this is usually a shared snippet like `header.php` or a layout file. Add the `seo/head` snippet to your `<head>` and the `seo/schemas` snippet before the `</body>` closing tag:

```php
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

Make sure your `<html>` tag also includes the `lang` attribute as shown above. Browsers use it for automatic hyphenation, and Google uses it to determine the language of your page.

Now open your site in a browser and view the page source. You should already see `<title>`, `<meta>` and Open Graph tags in your `<head>`. The plugin fills them with sensible defaults out of the box.

## Editing meta tags in the panel

Next, you want to give your editors control over the SEO fields. Add the SEO tab to your site blueprint:

```yaml
# site/blueprints/site.yml
tabs:
  content:
    fields:
      # move your existing fields here
  seo: seo # <--- add this
```

This gives you global defaults for meta titles, descriptions and social images. Any page without its own SEO settings will use these.
Learn more about how defaults work in [Your first Meta Tags](0_getting-started/1_your-first-meta-tags).

And now add the SEO tab to any page blueprint where editors should be able to override the defaults:

```yaml
# site/blueprints/pages/default.yml
tabs:
  content:
    fields:
      # move your existing fields here
  seo: seo # <--- add this
```

Open the Panel and navigate to any page. You'll see a new SEO tab with fields for meta title, description, social images and more.

Try it: enter a custom meta title, save, and reload the page in your browser. View the source, your title is there.

Now delete the title you just entered and reload again. The plugin falls back to your page's regular title.

This is the **Meta Cascade**, the plugin always finds the best available value, so you only need to fill in fields when you want to override the default. [Learn more about the Meta Cascade](0_getting-started/1_your-first-meta-tags).

## Set your canonical URL

To prevent duplicate content issues (e.g. if your site is reachable with and without `www`), tell the plugin which URL is the canonical one:

```php
// site/config/config.php
return [
	// [...]
	'tobimori.seo' => [
		'canonical' => [
			'base' => 'https://www.example.com',
		],
	]
];
```

Reload your page and check the source. You'll see a `<link rel="canonical">` tag pointing to your configured domain.

## Single-language setup

If you're not using Kirby's [multi-language feature](https://getkirby.com/docs/guide/languages), set your language code so the plugin can generate the correct `og:locale` tag:

```php
// site/config/config.php
return [
  // [...]
  'tobimori.seo' => [
    'canonical' => [
      'base' => 'https://www.example.com',
    ],
    'locale' => 'en_US',
  ],
];
```

If you already added the canonical config above, add `lang` to the same `tobimori.seo` block.

If you already have multi-language set up in Kirby the plugin will pick up the language automatically.

## Purchase license & activate your installation

Once you publish your website, you need to purchase a Kirby SEO license. We will send you a unique license code for your domain. You can activate your license with the following steps:

1. Open the Panel at `https://example.com/panel` and log in.
2. Click on the "Metadata & SEO" tab, and click on "Activate" in the top right.
3. Enter your license code and your email address and press "Activate".

It is not required to activate your license locally.

## Where to go from here
