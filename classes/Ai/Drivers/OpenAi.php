<?php

namespace tobimori\Seo\Ai\Drivers;

use Generator;
use Kirby\Exception\Exception as KirbyException;
use tobimori\Seo\Ai\Chunk;
use tobimori\Seo\Ai\Driver;

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

		// Responses API accepts strings, arrays of content blocks, or message lists.
		if (isset($context['input']) === true) {
			$payload['input'] = $context['input'];
		} elseif (isset($context['messages']) === true) {
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

		$options = [
			'http' => [
				'method' => 'POST',
				'header' => implode("\r\n", $headers),
				'content' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
				'ignore_errors' => true,
				'protocol_version' => 1.1,
			]
		];

		$contextResource = stream_context_create($options);
		$handle = @fopen($endpoint, 'rb', false, $contextResource);

		if ($handle === false) {
			throw new KirbyException('Failed to establish OpenAI stream.');
		}

		try {
			$meta    = stream_get_meta_data($handle);
			$headers = $meta['wrapper_data'] ?? [];
			$status  = $this->extractStatusCode($headers);

			if ($status !== null && $status >= 400) {
				$body = stream_get_contents($handle) ?: '';
				throw new KirbyException(sprintf(
					'OpenAI request failed (%d): %s',
					$status,
					$this->summarizeBody($body)
				));
			}

			stream_set_blocking($handle, true);
			stream_set_timeout($handle, 60);

			while (!feof($handle)) {
				$line = fgets($handle);

				if ($line === false) {
					$meta = stream_get_meta_data($handle);
					if (($meta['timed_out'] ?? false) === true) {
						throw new KirbyException('OpenAI stream timed out.');
					}

					break;
				}

				$line = trim($line);

				// skip keep-alive newlines and unrelated prefixes
				if ($line === '' || str_starts_with($line, ':')) {
					continue;
				}

				if (str_starts_with($line, 'data:') === false) {
					continue;
				}

				$payload = trim(substr($line, 5));

				if ($payload === '' || $payload === '[DONE]') {
					yield Chunk::done();
					break;
				}

				$event = json_decode($payload, true);

				if (json_last_error() !== JSON_ERROR_NONE || $event === null) {
					continue;
				}

				$type = $event['type'] ?? null;

				if ($type === 'response.error') {
					$message = $event['error']['message'] ?? 'Unknown OpenAI streaming error.';
					throw new KirbyException($message);
				}

				if ($type === 'response.output_text.delta') {
					$delta = $event['delta'] ?? '';

					if ($delta !== '') {
						yield Chunk::textDelta($delta);
					}

					continue;
				}

				if ($type === 'response.completed') {
					yield Chunk::done($event['response'] ?? null);
					break;
				}
			}
		} finally {
			fclose($handle);
		}
	}

	private function extractStatusCode(array $headers): int|null
	{
		$statusLine = $headers[0] ?? null;

		if ($statusLine === null) {
			return null;
		}

		if (preg_match('/HTTP\/\d(?:\.\d)?\s+(\d{3})/', $statusLine, $matches) === 1) {
			return (int)$matches[1];
		}

		return null;
	}

	private function summarizeBody(string $body, int $limit = 200): string
	{
		$body = trim($body);

		if (strlen($body) <= $limit) {
			return $body;
		}

		return substr($body, 0, $limit - 3) . '...';
	}
}
