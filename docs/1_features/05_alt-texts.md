---
title: Alt Text Field
intro: Structured alt text for images, with AI generation and a decorative toggle
---

Every image on the web needs an `alt` attribute. Images that convey meaning need descriptive text. Decorative images need an empty `alt=""`, which tells screen readers to skip them entirely. Getting this wrong hurts accessibility.

Kirby SEO provides a dedicated `alt-text` field that handles both cases. It stores structured data instead of a plain string, so your templates always render the correct HTML attributes.

## Adding the field

Add a `alt-text` field to any file blueprint:

```yaml
# site/blueprints/files/image.yml
fields:
  alt:
    type: alt-text
    label: Alt Text
```

Editors see a text input with a toggle. The toggle marks an image as decorative: when active, the text input disappears because decorative images don't need a description.

## AI generation

If [AI Assist](1_features/04_ai-assist) is configured, the field shows **Generate** and **Customize** buttons. The AI sees the actual image and writes alt text based on it, the filename, and the page context. Results stream in word by word and can be stopped early.

You can disable AI for a specific field by setting `ai: false` in the blueprint.

### Auto-generation on upload

Set `autogenerate: true` to generate alt text automatically when an image is uploaded:

```yaml
alt:
  type: alt-text
  autogenerate: true
```

By default, this runs synchronously during the upload. For better performance, you can offload it to a background queue. See [Background Processing](2_customization/10_background-processing) for setup. On multilingual sites, a single AI call generates alt text for all languages at once.

## Using alt text in templates

The plugin registers a `toAltText()` field method that returns an `AltText` object. Use its `toAttr()` method to get the correct HTML attributes, then spread them into your image helper:

```php
<?= Html::img($file->url(), [
  'width' => $file->width(),
  'height' => $file->height(),
  ...$file->alt()->toAltText()->toAttr(),
]) ?>
// <img alt="A dog playing fetch" src="..." width="..." height="...">

// decorative image:
// <img alt="" src="..." width="..." height="...">

The field also works with plain string values from existing `alt` fields. If you migrate from a regular text field, `toAltText()` treats the old value as manual alt text.
