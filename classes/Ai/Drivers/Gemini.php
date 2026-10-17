<?php

namespace tobimori\Seo\Ai\Drivers;

use Generator;
use tobimori\Seo\Ai\Chunk;
use tobimori\Seo\Ai\Content;
use tobimori\Seo\Ai\Driver;
use tobimori\Seo\Ai\SseStream;

class Gemini extends Driver
{
	protected const string DEFAULT_ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta';
	protected const string DEFAULT_MODEL = 'gemini-3.5-flash-lite';

	/**
	 * @inheritDoc
	 */
	public function stream(
		array $content,
		string|null $model = null
	): Generator {
		$apiKey = $this->config('apiKey', required: true);
		$model ??= $this->config('model', static::DEFAULT_MODEL);
		$baseEndpoint = $this->config('endpoint', static::DEFAULT_ENDPOINT);
		$endpoint = "{$baseEndpoint}/interactions";

		$headers = [
			'Content-Type: application/json',
			"x-goog-api-key: {$apiKey}"
		];

		$payload = [
			'model' => $model,
			'input' => $this->buildSteps($content),
			'stream' => true,
			// keep requests stateless, nothing is retained server-side
			'store' => false
		];

		$systemInstruction = $this->buildSystemInstruction($content);
		if ($systemInstruction !== null) {
			$payload['system_instruction'] = $systemInstruction;
		}

		$stream = new SseStream($endpoint, $headers, $payload, (int)$this->config('timeout', 120));
		$started = false;

		yield from $stream->stream(static function (array $event) use (&$started): Generator {
			switch ($event['event_type'] ?? null) {
				case 'error':
					$message = $event['error']['message'] ?? null;
					yield Chunk::error(is_string($message) ? $message : 'Unknown Gemini error.', $event);
					return;

				case 'interaction.created':
					if (!$started) {
						yield Chunk::streamStart($event);
						yield Chunk::textStart($event);
						$started = true;
					}
					return;

				case 'step.delta':
					$delta = $event['delta'] ?? [];
					$text = ($delta['type'] ?? null) === 'text' ? $delta['text'] ?? null : null;
					if (is_string($text) && $text !== '') {
						yield Chunk::textDelta($text, $event);
					}
					return;

				case 'interaction.completed':
					$status = $event['interaction']['status'] ?? 'completed';
					if ($status === 'failed' || $status === 'cancelled') {
						yield Chunk::error("Gemini interaction {$status}.", $event);
						return;
					}

					yield Chunk::textComplete($event);
					yield Chunk::streamEnd($event);
					return;
			}
		});
	}

	/**
	 * Translates an array of Content messages into Interactions API steps.
	 *
	 * @param array<Content> $content
	 */
	private function buildSteps(array $content): array
	{
		$steps = [];

		foreach ($content as $message) {
			if ($message->role() === 'system') {
				continue;
			}

			$parts = [];
			foreach ($message->blocks() as $block) {
				if ($block['type'] === 'image') {
					$parts[] = [
						'type' => 'image',
						'mime_type' => $block['mediaType'],
						'data' => $block['data']
					];
				} else {
					$parts[] = [
						'type' => 'text',
						'text' => $block['text']
					];
				}
			}

			$steps[] = [
				'type' => $message->role() === 'assistant' ? 'model_output' : 'user_input',
				'content' => $parts
			];
		}

		return $steps;
	}

	/**
	 * Joins system messages into a single system instruction string.
	 *
	 * @param array<Content> $content
	 */
	private function buildSystemInstruction(array $content): string|null
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
