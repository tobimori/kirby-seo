---
title: AI Assist
intro: Let AI draft your meta titles and descriptions
---

AI Assist can generate meta titles and descriptions based on the actual content of each page. It reads the page, looks at your existing meta fields, and drafts a title or description that fits.

AI Assist works for these fields:

- Meta title
- Meta description
- Open Graph description
- Site-level meta description
- Site-level Open Graph description

AI Assist asks the model to write in the language of your page and take your title template length into account. You can tweak the result before saving.

## Setting up a provider

AI Assist needs an API key from an AI provider. Kirby SEO supports these providers out of the box:

- [OpenAI](https://platform.openai.com/) (`openai`)
- [Anthropic / Claude](https://platform.claude.com/) (`anthropic`)
- [Google Gemini](https://aistudio.google.com/api-keys) (`gemini`)
- [OpenRouter](https://openrouter.ai/) (`openrouter`), which gives you access to models from several providers through one API

Pick whichever you want to use and create an API key in its dashboard. API usage is separate from your Kirby SEO license; check your provider's pricing for its current costs and limits.

If you have a Claude Max or Team plan, you can also use its [included API credits](https://platform.claude.com/docs/en/about-claude/api-credits-for-subscribers). Link your plan to a Claude Console organization in your billing settings, then create a Console API key and use it with the `anthropic` provider. The credits don't apply to OpenRouter.

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
          ],
        ],
      ],
    ],
  ],
];
```

To use another provider, change both occurrences of `openrouter` to its ID from the list above and use its API key.

Each provider comes with a default model. You can choose a different one by adding a `model` key next to `apiKey`. See the [AI Assist options](3_reference/0_options#ai-assist) for the current defaults and [Customizing AI Assist](2_customization/05_ai-assist) for more configuration options.

## Using AI Assist in the Panel

The provider config is a one-time setup by the developer. Once it's in place, editors just use the buttons in the Panel.

You'll see new buttons next to the meta title and description fields in the SEO tab.

The **Generate** button drafts a new value from scratch based on the page content. If the field already has a value, it changes to **Regenerate**. If you want more control, click **Customize** to add your own instructions before generating, like "keep it under 50 characters" or "focus on the pricing".

Already have a value but want to tweak it? The **Edit** button lets you revise the current text with instructions like "make it shorter" or "add the brand name".

The result streams in chunks. You can stop it early if you want.

## Custom providers and prompts

You can add your own providers or override the built-in prompts. See [Customizing AI Assist](2_customization/05_ai-assist) for details.
