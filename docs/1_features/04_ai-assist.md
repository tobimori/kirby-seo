---
title: AI Assist
intro: Let AI draft your meta titles and descriptions
---

Writing meta titles and descriptions for every page gets tedious fast. AI Assist can generate them for you based on the actual content of each page. It reads the page, looks at your existing meta fields, and drafts a title or description that fits.

AI Assist works for these fields:

- Meta title
- Meta description
- OG description
- Site-level meta description
- Site-level OG description

The generated text matches the language of your page and respects your title template length, so titles don't get cut off in search results.

## Setting up a provider

AI Assist needs an API key from an AI provider. Kirby SEO supports [OpenAI](https://platform.openai.com/), [Anthropic](https://console.anthropic.com/), and [OpenRouter](https://openrouter.ai/) out of the box. OpenRouter is a good starting point because it gives you access to many models through a single API, including models with free tiers.

Here's an example using OpenRouter:

```php
// site/config/config.php
return [
  'tobimori.seo' => [
    'ai' => [
      'provider' => 'openrouter',
      'providers' => [
        'openrouter' => [
          'config' => [
            'apiKey' => 'sk-or-...',
            'model' => 'google/gemini-3-flash-preview',
          ],
        ],
      ],
    ],
  ],
];
```

For generating meta titles and descriptions, you don't need the most powerful model. Small, fast models work well and keep costs low. Our recommendation is **Google Gemini 3 Flash Preview** (Google Provider is coming soon): it's fast, capable, and has a generous free tier.

You can change the model for any provider via the `model` key in the config, as shown in the example above.

For config options for all providers, see [customization/ai-assist](Customizing AI Assist) for details.

## Using AI Assist in the Panel

Once a provider is configured, you'll see new buttons next to the meta title and description fields in the SEO tab.

The **Generate** button drafts a new value from scratch based on the page content. If the field already has a value, it changes to **Regenerate**. If you want more control, click **Customize** to add your own instructions before generating, like "keep it under 50 characters" or "focus on the pricing".

Already have a value but want to tweak it? The **Edit** button lets you revise the current text with instructions like "make it shorter" or "add the brand name".

The result streams in live. You can stop it early if you want.

## Custom providers and prompts

You can add your own providers or override the built-in prompts. See [customization/ai-assist](Customizing AI Assist) for details.
