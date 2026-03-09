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
	protected const string DEFAULT_MODEL = 'gemini-3.1-flash-lite-preview';

	/**
	 * @inheritDoc
	 */
	public function stream(
		array $content,
		string|null $model = null,
	): Generator {
		$apiKey = $this->config('apiKey', required: true);
		$model = $model ?? $this->config('model', static::DEFAULT_MODEL);
		$baseEndpoint = $this->config('endpoint', static::DEFAULT_ENDPOINT);
		$endpoint = "{$baseEndpoint}/models/{$model}:streamGenerateContent?alt=sse&key={$apiKey}";

		$headers = [
			'Content-Type: application/json',
		];

		$payload = [
			'contents' => $this->buildContents($content),
		];

		$systemInstruction = $this->buildSystemInstruction($content);
		if ($systemInstruction !== null) {
			$payload['systemInstruction'] = $systemInstruction;
		}

		$stream = new SseStream($endpoint, $headers, $payload, (int)$this->config('timeout', 120));
		$started = false;

		yield from $stream->stream(function (array $event) use (&$started): Generator {
			$candidates = $event['candidates'] ?? [];
			$candidate = $candidates[0] ?? null;

			if ($candidate === null) {
				$error = $event['error'] ?? null;
				if ($error) {
					yield Chunk::error($error['message'] ?? 'Unknown Gemini error.', $event);
				}
				return;
			}

			if (!$started) {
				yield Chunk::streamStart($event);
				yield Chunk::textStart($event);
				$started = true;
			}

			$finishReason = $candidate['finishReason'] ?? null;
			if ($finishReason === 'SAFETY') {
				yield Chunk::error('Response blocked by safety filters.', $event);
				return;
			}

			$parts = $candidate['content']['parts'] ?? [];
			foreach ($parts as $part) {
				$text = $part['text'] ?? '';
				if ($text !== '') {
					yield Chunk::textDelta($text, $event);
				}
			}

			if ($finishReason !== null) {
				yield Chunk::textComplete($event);
				yield Chunk::streamEnd($event);
			}
		});
	}

	/**
	 * Translates an array of Content messages into the Gemini contents format.
	 *
	 * @param array<Content> $content
	 */
	private function buildContents(array $content): array
	{
		$contents = [];

		foreach ($content as $message) {
			if ($message->role() === 'system') {
				continue;
			}

			$parts = [];
			foreach ($message->blocks() as $block) {
				if ($block['type'] === 'image') {
					$parts[] = [
						'inline_data' => [
							'mime_type' => $block['mediaType'],
							'data' => $block['data'],
						],
					];
				} elseif ($block['type'] === 'text') {
					$parts[] = [
						'text' => $block['text'],
					];
				}
			}

			$contents[] = [
				'role' => $message->role() === 'assistant' ? 'model' : 'user',
				'parts' => $parts,
			];
		}

		return $contents;
	}

	/**
	 * Extracts system messages into a Gemini systemInstruction object.
	 *
	 * @param array<Content> $content
	 */
	private function buildSystemInstruction(array $content): array|null
	{
		$parts = [];

		foreach ($content as $message) {
			if ($message->role() !== 'system') {
				continue;
			}

			foreach ($message->blocks() as $block) {
				if ($block['type'] === 'text') {
					$parts[] = ['text' => $block['text']];
				}
			}
		}

		if ($parts === []) {
			return null;
		}

		return ['parts' => $parts];
	}
}
