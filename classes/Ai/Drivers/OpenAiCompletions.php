<?php

namespace tobimori\Seo\Ai\Drivers;

use Generator;
use tobimori\Seo\Ai\Chunk;
use tobimori\Seo\Ai\Content;
use tobimori\Seo\Ai\Driver;
use tobimori\Seo\Ai\SseStream;

use function is_array;
use function is_string;

/**
 * Driver for the OpenAI Chat Completions API (legacy) and any OpenAI-compatible
 * endpoint exposing `/chat/completions`, such as Cloudflare AI Gateway, OpenRouter,
 * Groq, Together, or self-hosted compat servers.
 */
class OpenAiCompletions extends Driver
{
	protected const string DEFAULT_ENDPOINT = 'https://api.openai.com/v1/chat/completions';
	protected const string DEFAULT_MODEL = 'gpt-5.4-nano';

	/**
	 * @inheritDoc
	 */
	public function stream(
		array $content,
		string|null $model = null,
	): Generator {
		$apiKey = $this->config('apiKey', required: true);
		$headers = [
			'Content-Type: application/json',
			'Accept: text/event-stream',
			"Authorization: Bearer {$apiKey}",
		];

		if ($organization = $this->config('organization')) {
			$headers[] = "OpenAI-Organization: {$organization}";
		}

		foreach ((array)$this->config('headers', []) as $name => $value) {
			$headers[] = "{$name}: {$value}";
		}

		$payload = [
			'model' => $model ?? $this->config('model', static::DEFAULT_MODEL),
			'messages' => $this->buildMessages($content),
			'stream' => true,
		];

		if ($maxTokens = $this->config('maxTokens')) {
			$payload['max_tokens'] = (int)$maxTokens;
		}

		$stream = new SseStream($this->config('endpoint', static::DEFAULT_ENDPOINT), $headers, $payload, (int)$this->config('timeout', 120));

		$started = false;
		$textStarted = false;

		yield from $stream->stream(function (array $event) use (&$started, &$textStarted): Generator {
			if (isset($event['error'])) {
				$error = $event['error'];
				$message = is_array($error) ? ($error['message'] ?? 'Unknown Chat Completions streaming error.') : (string)$error;
				yield Chunk::error($message, $event);
				return;
			}

			$choice = $event['choices'][0] ?? null;
			if ($choice === null) {
				return;
			}

			if (!$started) {
				yield Chunk::streamStart($event);
				$started = true;
			}

			$delta = $choice['delta'] ?? [];
			$text = $delta['content'] ?? null;

			if (is_string($text) && $text !== '') {
				if (!$textStarted) {
					yield Chunk::textStart($event);
					$textStarted = true;
				}

				yield Chunk::textDelta($text, $event);
			}

			$finishReason = $choice['finish_reason'] ?? null;
			if ($finishReason !== null) {
				if ($finishReason === 'content_filter') {
					yield Chunk::error('Response blocked by content filter.', $event);
					return;
				}

				if ($textStarted) {
					yield Chunk::textComplete($event);
				}

				yield Chunk::streamEnd($event);
			}
		});
	}

	/**
	 * Translates an array of Content messages into the Chat Completions messages format.
	 * Text-only messages use the legacy string `content` shape for broader compat; messages
	 * with images use the multi-modal parts array.
	 *
	 * @param array<Content> $content
	 */
	private function buildMessages(array $content): array
	{
		$messages = [];

		foreach ($content as $message) {
			$blocks = $message->blocks();
			$hasImage = false;

			foreach ($blocks as $block) {
				if ($block['type'] === 'image') {
					$hasImage = true;
					break;
				}
			}

			if (!$hasImage) {
				$text = '';
				foreach ($blocks as $block) {
					if ($block['type'] === 'text') {
						$text .= $block['text'];
					}
				}

				$messages[] = [
					'role' => $message->role(),
					'content' => $text,
				];
				continue;
			}

			$parts = [];
			foreach ($blocks as $block) {
				if ($block['type'] === 'image') {
					$parts[] = [
						'type' => 'image_url',
						'image_url' => [
							'url' => "data:{$block['mediaType']};base64,{$block['data']}",
						],
					];
				} elseif ($block['type'] === 'text') {
					$parts[] = [
						'type' => 'text',
						'text' => $block['text'],
					];
				}
			}

			$messages[] = [
				'role' => $message->role(),
				'content' => $parts,
			];
		}

		return $messages;
	}
}
