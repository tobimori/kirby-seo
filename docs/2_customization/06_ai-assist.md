---
title: Customizing AI Assist
intro: Override prompts or add your own AI provider
---

## Overriding prompts

AI Assist uses Kirby snippets for its prompts. You can override any of them by creating a snippet with the same path in your project.

The built-in prompt snippets are:

- `seo/prompts/tasks/title` - Meta title generation
- `seo/prompts/tasks/description` - Meta description generation
- `seo/prompts/tasks/og-description` - Open Graph description generation
- `seo/prompts/tasks/site-description` - Site-level meta description
- `seo/prompts/tasks/og-site-description` - Site-level OG description

To override the meta title prompt, create `site/snippets/seo/prompts/tasks/title.php` in your project. Kirby's snippet loading will pick up your version instead of the built-in one.

Each prompt snippet receives these variables:

- `$page` - the current page
- `$site` - the site object
- `$instructions` - custom instructions from the editor (if any)
- `$edit` - the existing text when editing (if any)

There are also shared snippets that the task prompts include:

- `seo/prompts/introduction` - Defines the AI's role and rules
- `seo/prompts/content` - Extracts the page content
- `seo/prompts/meta` - Shows existing metadata for context

You can override these too. Look at the built-in prompts in `site/plugins/kirby-seo/snippets/prompts/` to understand their structure before writing your own.

## Adding a custom provider

If you need a provider that isn't built in, you can add your own. A provider has two parts: a driver class that handles the API communication, and a config entry that registers it.

Create a class that extends `tobimori\Seo\Ai\Driver`. The only method you need to implement is `stream`, which receives a prompt string and must yield `Chunk` objects as the response comes in.

```php
<?php

namespace App\Ai;

use Generator;
use tobimori\Seo\Ai\Chunk;
use tobimori\Seo\Ai\Driver;
use tobimori\Seo\Ai\SseStream;

class MyProvider extends Driver
{
  public function stream(string $prompt, string|null $model = null): Generator
  {
    $apiKey = $this->config('apiKey', required: true);
    $model = $model ?? $this->config('model', 'default-model');
    $endpoint = $this->config('endpoint', required: true);

    $stream = new SseStream($endpoint, [
      'Content-Type: application/json',
      'Accept: text/event-stream',
      "Authorization: Bearer {$apiKey}",
    ], [
      'model' => $model,
      'input' => $prompt,
      'stream' => true,
    ], (int)$this->config('timeout', 120));

    yield from $stream->stream(function (array $event): Generator {
      $type = $event['type'] ?? null;

      if ($type === 'start') {
        yield Chunk::streamStart($event);
      }

      if ($type === 'delta') {
        yield Chunk::textDelta($event['text'] ?? '', $event);
      }

      if ($type === 'done') {
        yield Chunk::streamEnd($event);
      }

      if ($type === 'error') {
        yield Chunk::error($event['message'] ?? 'Unknown error', $event);
      }
    });
  }
}
```

The driver uses `$this->config()` to read values from the provider's `config` array in `config.php`. Pass `required: true` to throw an error if a value is missing.

`SseStream` is a helper class included in Kirby SEO that handles the cURL request and SSE parsing. You pass it the endpoint, headers, payload, and a mapper function that converts raw SSE events into `Chunk` objects.

If your API doesn't use SSE, you can skip `SseStream` and yield chunks directly.

The chunks the Panel expects, in order:

1. `Chunk::streamStart()` - Signals the stream has started
2. `Chunk::textDelta($text)` - Each piece of generated text (repeated)
3. `Chunk::textComplete()` - The text is done
4. `Chunk::streamEnd()` - The stream is finished

If something goes wrong, yield `Chunk::error($message)` at any point.

## Registering the provider

Add your driver to the config and set it as the active provider:

```php
<?php
// site/config/config.php

return [
  'tobimori.seo' => [
    'ai' => [
      'provider' => 'myprovider',
      'providers' => [
        'myprovider' => [
          'driver' => \App\Ai\MyProvider::class,
          'config' => [
            'apiKey' => 'sk-...',
            'model' => 'my-model',
            'endpoint' => 'https://api.example.com/v1/chat',
          ],
        ],
      ],
    ],
  ],
];
```

See the built-in drivers in `site/plugins/kirby-seo/classes/Ai/Drivers/` for complete implementations.
