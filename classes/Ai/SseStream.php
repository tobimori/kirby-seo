<?php

namespace tobimori\Seo\Ai;

use CurlHandle;
use Generator;
use Kirby\Data\Json;
use Kirby\Exception\Exception as KirbyException;

use function curl_errno;
use function curl_error;
use function curl_getinfo;
use function curl_init;
use function curl_multi_add_handle;
use function curl_multi_close;
use function curl_multi_exec;
use function curl_multi_init;
use function curl_multi_remove_handle;
use function curl_multi_select;
use function curl_setopt_array;
use function is_array;
use function is_string;
use function sprintf;
use function strlen;

use const CURLINFO_HTTP_CODE;
use const CURLM_CALL_MULTI_PERFORM;
use const CURLOPT_HTTPHEADER;
use const CURLOPT_POST;
use const CURLOPT_POSTFIELDS;
use const CURLOPT_RETURNTRANSFER;
use const CURLOPT_TIMEOUT;
use const CURLOPT_WRITEFUNCTION;

final class SseStream
{
	private const int ERROR_CONTEXT_LIMIT = 8192;

	public function __construct(
		private readonly string $endpoint,
		private readonly array $headers,
		private readonly array $payload,
		private readonly int $timeout = 120
	) {
	}

	/**
	 * @param callable(array $event): Generator<int, Chunk, mixed, void> $mapper
	 * @return Generator<int, Chunk, mixed, void>
	 */
	public function stream(callable $mapper): Generator
	{
		$buffer = '';
		$response = '';
		$handle = curl_init($this->endpoint);

		curl_setopt_array($handle, [
			CURLOPT_POST => true,
			CURLOPT_HTTPHEADER => $this->headers,
			CURLOPT_POSTFIELDS => Json::encode($this->payload),
			CURLOPT_RETURNTRANSFER => false,
			CURLOPT_TIMEOUT => $this->timeout,
			CURLOPT_WRITEFUNCTION => static function (CurlHandle $curl, string $data) use (&$buffer, &$response): int {
				$buffer .= $data;
				$currentLength = strlen($response);

				if ($currentLength < self::ERROR_CONTEXT_LIMIT) {
					$response .= substr($data, 0, self::ERROR_CONTEXT_LIMIT - $currentLength);
				}

				return strlen($data);
			}
		]);

		$multi = curl_multi_init();
		curl_multi_add_handle($multi, $handle);

		try {
			$running = null;
			do {
				$status = curl_multi_exec($multi, $running);

				if ($status === CURLM_CALL_MULTI_PERFORM) {
					continue;
				}

				yield from $this->drainBuffer($buffer, $mapper);

				if ($running) {
					curl_multi_select($multi, 0.1);
				}
			} while ($running);

			yield from $this->drainBuffer($buffer, $mapper, true);

			$errno = curl_errno($handle);
			if ($errno) {
				throw new KirbyException(curl_error($handle) ?: "Streaming request failed (cURL error {$errno}).");
			}

			$code = (int)curl_getinfo($handle, CURLINFO_HTTP_CODE);
			if ($code >= 400) {
				$message = sprintf('Streaming request failed (%d)', $code);
				$body = trim($response);

				if ($body !== '') {
					$decoded = json_decode($body, true);

					if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
						$detail = $decoded['error']['message'] ?? $decoded['message'] ?? null;
						$body = is_string($detail) ? $detail : $body;
					}

					if (strlen($body) > 200) {
						$body = substr($body, 0, 200) . '...';
					}

					$message .= ': ' . (preg_replace('/\s+/', ' ', $body) ?? $body);
				}

				throw new KirbyException($message);
			}
		} finally {
			curl_multi_remove_handle($multi, $handle);
			curl_multi_close($multi);
		}
	}

	/**
	 * @param callable(array $event): Generator<int, Chunk, mixed, void> $mapper
	 * @return Generator<int, Chunk, mixed, void>
	 */
	private function drainBuffer(string &$buffer, callable $mapper, bool $final = false): Generator
	{
		$match = [];

		while (
			preg_match('/\r?\n\r?\n/', $buffer, $match, PREG_OFFSET_CAPTURE) === 1
		) {
			/** @var int $pos */
			$pos = $match[0][1];
			$len = strlen($match[0][0]);
			$frame = substr($buffer, 0, $pos);
			$buffer = substr($buffer, $pos + $len);

			foreach ($this->mapFrame($frame, $mapper) as $chunk) {
				yield $chunk;
			}
		}

		if ($final && trim($buffer) !== '') {
			foreach ($this->mapFrame($buffer, $mapper) as $chunk) {
				yield $chunk;
			}

			$buffer = '';
		}
	}

	/**
	 * @param callable(array $event): Generator<int, Chunk, mixed, void> $mapper
	 * @return Generator<int, Chunk, mixed, void>
	 */
	private function mapFrame(string $frame, callable $mapper): Generator
	{
		$frame = trim($frame);

		if ($frame === '') {
			return;
		}

		$payload = '';

		foreach (preg_split("/\r\n|\n|\r/", $frame) ?: [] as $line) {
			$line = trim($line);

			if ($line === '' || str_starts_with($line, ':')) {
				continue;
			}

			if (str_starts_with($line, 'data:')) {
				$payload .= substr($line, 5);
			}
		}

		$payload = trim($payload);
		if ($payload === '' || $payload === '[DONE]') {
			return;
		}

		$event = json_decode($payload, true);
		if (json_last_error() !== JSON_ERROR_NONE || !is_array($event)) {
			return;
		}

		yield from $mapper($event);
	}
}
