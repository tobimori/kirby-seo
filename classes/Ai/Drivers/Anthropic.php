<?php

namespace tobimori\Seo\Ai\Drivers;

use Generator;
use tobimori\Seo\Ai\Chunk;
use tobimori\Seo\Ai\Content;
use tobimori\Seo\Ai\Driver;
use tobimori\Seo\Ai\SseStream;

class Anthropic extends Driver
{
	protected const string DEFAULT_ENDPOINT = 'https://api.anthropic.com/v1/messages';
	protected const string DEFAULT_MODEL = 'claude-haiku-5-5';

	/**
	 * @inheritDoc
	 */
	public function stream(
		array $content,
		string|null $model = null
	): Generator {
		$apiKey = $this->config('apiKey', required: true);
		$headers = [
			'Content-Type: application/json',
			'Accept: text/event-stream',
			"x-api-key: {$apiKey}",
			'anthropic-version: 2023-06-01'
		];

		$payload = [
			'model' => $model ?? $this->config('model', static::DEFAULT_MODEL),
			'messages' => $this->buildMessages($content),
			'max_tokens' => 4096,
			'stream' => true
		];

		$system = $this->buildSystem($content);
		if ($system !== null) {
			$payload['system'] = $system;
		}

		$stream = new SseStream((string)$this->config('endpoint', static::DEFAULT_ENDPOINT), $headers, $payload, (int)$this->config('timeout', 120));
		yield from $stream->stream(static function (array $event): Generator {
			$type = $event['type'] ?? null;

			// handle message start event
			if ($type === 'message_start') {
				yield Chunk::streamStart($event);
				return;
			}

			// handle content block start (beginning of text output)
			if ($type === 'content_block_start') {
				$contentBlock = $event['content_block'] ?? [];
				if (($contentBlock['type'] ?? null) === 'text') {
					yield Chunk::textStart($event);
				}
				return;
			}

			// handle content block delta (text chunks)
			if ($type === 'content_block_delta') {
				$delta = $event['delta'] ?? [];
				if (($delta['type'] ?? null) === 'text_delta') {
					$text = $delta['text'] ?? null;
					if (is_string($text) && $text !== '') {
						yield Chunk::textDelta($text, $event);
					}
				}
				return;
			}

			// handle content block stop (end of text block)
			if ($type === 'content_block_stop') {
				yield Chunk::textComplete($event);
				return;
			}

			// handle message stop (end of stream)
			if ($type === 'message_stop') {
				yield Chunk::streamEnd($event);
				return;
			}

			// handle ping events (keep-alive)
			if ($type === 'ping') {
				// ignore ping events
				return;
			}

			// handle error events
			if ($type === 'error') {
				$error = $event['error'] ?? [];
				$message = $error['message'] ?? null;
				yield Chunk::error(is_string($message) ? $message : 'Unknown Anthropic streaming error.', $event);
				return;
			}

			// handle message delta (contains usage info)
			if ($type === 'message_delta') {
				// we could extract usage info here if needed
				return;
			}
		});
	}

	/**
	 * Translates an array of Content messages into the Anthropic messages format.
	 *
	 * @param array<Content> $content
	 */
	private function buildMessages(array $content): array
	{
		$messages = [];

		foreach ($content as $message) {
			if ($message->role() === 'system') {
				continue;
			}

			$blocks = [];
			foreach ($message->blocks() as $block) {
				if ($block['type'] === 'image') {
					$blocks[] = [
						'type' => 'image',
						'source' => [
							'type' => 'base64',
							'media_type' => $block['mediaType'],
							'data' => $block['data']
						]
					];
				} else {
					$blocks[] = [
						'type' => 'text',
						'text' => $block['text']
					];
				}
			}

			$messages[] = [
				'role' => $message->role(),
				'content' => $blocks
			];
		}

		return $messages;
	}

	/**
	 * Joins system messages into the top-level system prompt.
	 *
	 * @param array<Content> $content
	 */
	private function buildSystem(array $content): string|null
	{
		$texts = [];

		foreach ($content as $message) {
			if ($message->role() !== 'system') {
				continue;
			}

			foreach ($message->blocks() as $block) {
				if ($block['type'] === 'text') {
					$texts[] = $block['text'];
				}
			}
		}

		return $texts === [] ? null : implode("\n\n", $texts);
	}
}
