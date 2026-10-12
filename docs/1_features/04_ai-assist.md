---
title: AI Assist
intro: Let AI draft your meta titles and descriptions
---

AI Assist drafts meta titles and descriptions from your page content and existing metadata. It can generate new text or revise an existing value with your instructions. You choose the AI provider that processes the requests.

It is available for these fields:

- Meta title
- Meta description
- Open Graph description
- Site-level meta description
- Site-level Open Graph description

The prompts ask the model to use the language and tone of your site and account for the space used by your title template. Generated text is a draft: review its accuracy, wording, and length before publishing.

The same provider configuration also powers [AI alt text generation](1_features/05_alt-texts).

## Supported providers

Kirby SEO includes integrations for four providers. Use the one that fits your account, budget, and data-handling requirements; no particular provider or model is required.

| Provider      | Config ID    | Connection and API key                                                                                                    |
| ------------- | ------------ | ------------------------------------------------------------------------------------------------------------------------- |
| OpenAI        | `openai`     | Connect directly to OpenAI with a key from the [OpenAI Platform](https://platform.openai.com/api-keys).                   |
| Anthropic     | `anthropic`  | Connect directly to Claude with a key from the [Claude Console](https://platform.claude.com/).                            |
| Google Gemini | `gemini`     | Connect directly to the Gemini API with a key from [Google AI Studio](https://aistudio.google.com/api-keys).              |
| OpenRouter    | `openrouter` | Access models from multiple vendors through OpenRouter with a key from [OpenRouter](https://openrouter.ai/settings/keys). |

Each integration has a default model. You can use that default or select another compatible model with the provider's `model` config option. For alt text generation, the selected model must support image input. See the [AI Assist options](3_reference/0_options#ai-assist) for the current defaults and available configuration options.

## Setting up a provider

Create an API key with your chosen provider, then set the active provider ID and its key in `site/config/config.php`. For example, this configuration uses OpenRouter with its built-in default model:

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

For a different provider, replace both occurrences of `openrouter` with `openai`, `anthropic`, or `gemini`, and use an API key issued by that provider. Add a `model` entry alongside `apiKey` if you want to override its default model; model IDs must match the selected provider's API.

Keep API keys on the server and out of public repositories. Provider setup is a developer task; editors don't need to enter a key in the Panel.

## Billing and data handling

API usage is handled by the selected provider and isn't included in your Kirby SEO license. Prices, rate limits, free-tier access, and subscription credits depend on the provider, model, and account, and can change. Check the current terms rather than assuming a request is free:

- [OpenAI pricing](https://developers.openai.com/api/docs/pricing)
- [Anthropic pricing](https://platform.claude.com/docs/en/about-claude/pricing)
- [Gemini API pricing](https://ai.google.dev/gemini-api/docs/pricing)
- [OpenRouter model pricing](https://openrouter.ai/models)

Claude Max (5x and 20x) and Team plans include [monthly API credits](https://platform.claude.com/docs/en/about-claude/api-credits-for-subscribers) that can be used with Kirby SEO. Claim them in your Claude billing settings by linking a Claude Console organization, then create a Console API key and configure it for the `anthropic` provider. These credits apply to the direct Claude API, not OpenRouter, and expire each billing cycle without rolling over. Free, Pro, and Enterprise plans aren't eligible for these included credits.

Generation sends the content used in the prompt to your selected provider; alt text generation also sends the image. Review the provider's privacy, retention, and data-use terms before enabling AI Assist for sensitive content.

## Using AI Assist in the Panel

Once a provider is configured, editors can use the AI buttons on supported fields in the SEO tab:

- **Generate** drafts a value from the content. When the field already has a value, the button becomes **Regenerate**.
- **Customize** lets you add instructions before generating, such as "keep it under 50 characters" or "focus on the pricing".
- **Edit** revises an existing value with instructions such as "make it shorter" or "add the brand name".
- **Stop** interrupts generation while the response is streaming.

The response appears as it is generated. Check the draft and adjust it as needed before saving and publishing your changes.

## Custom providers and prompts

You can override the built-in prompts or register another provider with a custom driver. See [Customizing AI Assist](2_customization/06_ai-assist) for details.
