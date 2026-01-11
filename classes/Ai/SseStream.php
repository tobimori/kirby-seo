<?php

namespace tobimori\Seo\Ai;

use Generator;
use Kirby\Exception\Exception as KirbyException;

use function curl_close;
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
use function strlen;
use function sprintf;
use function is_array;

use const CURLOPT_HTTPHEADER;
use const CURLOPT_POST;
use const CURLOPT_POSTFIELDS;
use const CURLOPT_RETURNTRANSFER;
use const CURLOPT_TIMEOUT;
use const CURLOPT_WRITEFUNCTION;
use const CURLINFO_HTTP_CODE;
use const CURLM_CALL_MULTI_PERFORM;

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
	 * @param callable(array $event): Generator<Chunk> $mapper
	 * @return Generator<Chunk>
	 */
	public function stream(callable $mapper): Generator
	{
		$buffer = '';
		$response = '';
		$handle = curl_init($this->endpoint);

		curl_setopt_array($handle, [
			CURLOPT_POST => true,
			CURLOPT_HTTPHEADER => $this->headers,
			CURLOPT_POSTFIELDS => json_encode(
				$this->payload,
				JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
			),
			CURLOPT_RETURNTRANSFER => false,
			CURLOPT_TIMEOUT => $this->timeout,
			CURLOPT_WRITEFUNCTION => static function ($curl, $data) use (&$buffer, &$response) {
				$buffer .= $data;
				$currentLength = strlen($response);

				if ($currentLength < self::ERROR_CONTEXT_LIMIT) {
					$response .= substr($data, 0, self::ERROR_CONTEXT_LIMIT - $currentLength);
				}

				return strlen($data);
			},
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
				throw new KirbyException(curl_error($handle) ?: 'Streaming request failed.', $errno);
			}

			$code = curl_getinfo($handle, CURLINFO_HTTP_CODE);
			if ($code !== null && $code >= 400) {
				$message = sprintf('Streaming request failed (%d)', $code);
				$body = trim($response);

				if ($body !== '') {
					$decoded = json_decode($body, true);

					if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
						$body = $decoded['error']['message'] ?? $decoded['message'] ?? $body;
					}

					if (strlen($body) > 200) {
						$body = substr($body, 0, 200) . '...';
					}

					$message .= ': ' . preg_replace('/\s+/', ' ', $body);
				}

				throw new KirbyException($message);
			}
		} finally {
			curl_multi_remove_handle($multi, $handle);
			curl_multi_close($multi);
			curl_close($handle);
		}
	}

	/**
	 * @param callable(array $event): Generator<Chunk> $mapper
	 * @return Generator<Chunk>
	 */
	private function drainBuffer(string &$buffer, callable $mapper, bool $final = false): Generator
	{
		while (
			preg_match('/\r?\n\r?\n/', $buffer, $match, PREG_OFFSET_CAPTURE) === 1
		) {
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
	 * @param callable(array $event): Generator<Chunk> $mapper
	 * @return Generator<Chunk>
	 */
	private function mapFrame(string $frame, callable $mapper): Generator
	{
		$frame = trim($frame);

		if ($frame === '') {
			return;
		}

		$payload = '';

		foreach (preg_split("/\r\n|\n|\r/", $frame) as $line) {
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
