# Advanced Usage

## Programmatic/Computed Content

By default, the second level of the cascade allows you to specify default content for the SEO fields using a page model.

This is especially helpful if you want a certain field to default to another field's content or you're using a plugin like [kirby-paparazzi](https://github.com/tobimori/kirby-paparazzi) to programmatically generate OG images. You can also use page models to add custom meta tags to your pages.

An example page model could look like this:

```php
<?php
// site/models/template.php

use Kirby\Cms\Page;

class TemplatePage extends Page
{
	public function metaDefaults(string $lang = null): array
	{
		$content = $this->content($lang);

		return [
			// you can use field names (from blueprint)
			'metaDescription' => $content->summary(),

			// or any meta tag
			'og:image' => "{$this->url()}.png",
			"og:image:width" => 1230,
			"og:image:height" => 600,

			// kirby-seo tries to guess the correct syntax of the tag
			// (e.g. open graph tags always use "property" and "content" attributes)
			// but you can also specify them manually
			[
				'tag' => 'meta',
				'attributes' => [
					"property" => 'og:image:alt',
					"content" => "An image showing the beautiful city of {$this->title()}"
				],
			]
		];
	}
}

```

## Extending Blueprints

> **NOTE**: Blueprints touch an internal part of kirby-seo and structure is potentially subject to change in future releases with an alternate way of modyifing provided.

Kirby SEO tries to be as minimal as possible, while still providing a complete SEO solution. This means that some features offer the ability to disable them (like the Robots fields).

Although sometimes, you might want to go the extra mile and change the provided blueprint completely, e.g. if certain fields are provided by programmatic content and you don't want to allow editors to change them.

In this case, you can extend the provided blueprints with your own fields. This is done by using the `extends` keyword in your blueprint.

```yaml
# site/blueprints/pages/template.yml

tabs:
  content:
    # your blueprint
  seo:
    extends: seo/page
    columns:
      main:
        fields:
          metaDescription:
            label: meta-description
            type: info
            text: Will be filled automatically from the field `summary`
```

You can find the original blueprint structure in the [blueprints folder](https://github.com/tobimori/kirby-seo/tree/main/blueprints) of this plugin.
