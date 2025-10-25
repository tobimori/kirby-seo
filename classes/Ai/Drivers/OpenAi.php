<?php

namespace tobimori\Seo\Ai\Drivers;

use Generator;
use tobimori\Seo\Ai\Chunk;
use tobimori\Seo\Ai\Driver;
use tobimori\Seo\Ai\SseStream;

class OpenAi extends Driver
{
	protected const string DEFAULT_ENDPOINT = 'https://api.openai.com/v1/responses';
	protected const string DEFAULT_MODEL = 'gpt-5-mini-2025-08-07';

	/**
	 * @inheritDoc
	 */
	public function stream(string $prompt, array $context = []): Generator
	{
		$apiKey = $this->config('apiKey', required: true);
		$model = $context['model'] ?? $this->config('model', static::DEFAULT_MODEL);
		$endpoint = $this->config('endpoint', static::DEFAULT_ENDPOINT);
		$input = $context['input'] ?? $prompt;
		$headers = [
			'Content-Type: application/json',
			'Accept: text/event-stream',
			"Authorization: Bearer {$apiKey}",
		];

		if ($organization = $this->config('organization')) {
			$headers[] = "OpenAI-Organization: {$organization}";
		}

		$payload = [
			'model'  => $model,
			'stream' => true,
		];

		if (isset($context['input'])) {
			$payload['input'] = $context['input'];
		} elseif (isset($context['messages'])) {
			$payload['input'] = $context['messages'];
		} else {
			$payload['input'] = $input;
		}

		if (isset($context['instructions'])) {
			$payload['instructions'] = $context['instructions'];
		}

		if (isset($context['metadata']) && is_array($context['metadata'])) {
			$payload['metadata'] = $context['metadata'];
		}

		$stream = new SseStream($endpoint, $headers, $payload, (int)$this->config('timeout', 120));
		yield from $stream->stream(function (array $event): Generator {
			$type = $event['type'] ?? null;

			if ($type === 'response.created') {
				yield Chunk::streamStart($event);
				return;
			}

			if ($type === 'response.in_progress') {
				yield Chunk::textStart($event);
				return;
			}

			if ($type === 'response.output_text.delta') {
				$delta = $event['delta'] ?? '';
				if ($delta !== '') {
					yield Chunk::textDelta($delta, $event);
				}
				return;
			}

			if ($type === 'response.output_text.done') {
				yield Chunk::textComplete($event);
				return;
			}

			if ($type === 'response.completed') {
				yield Chunk::streamEnd($event);
				return;
			}

			if ($type === 'response.output_item.added' && ($event['item']['type'] ?? null) === 'reasoning') {
				yield Chunk::thinkingStart($event);
				return;
			}

			if ($type === 'response.error') {
				$message = $event['error']['message'] ?? 'Unknown OpenAI streaming error.';
				yield Chunk::error($message, $event);
			}
		});
	}
}
