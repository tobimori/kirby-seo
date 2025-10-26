<?php

namespace tobimori\Seo\Ai;

use Generator;
use Kirby\Exception\InvalidArgumentException;

abstract class Driver
{
	public function __construct(protected array $config = [])
	{
	}

	/**
	 * Streams a response for the given prompt and optional context data.
	 *
	 * @param string $prompt User prompt (e.g. Tasks).
	 * @param string|null $model Model to use.
	 *
	 * @return Generator<int, Chunk, mixed, void>
	 */
	abstract public function stream(string $prompt, string|null $model = null): Generator;

	/**
	 * Returns a configuration value or throws when required.
	 */
	protected function config(string $key, mixed $default = null, bool $required = false): mixed
	{
		$value = $this->config[$key] ?? $default;

		if ($required === true && ($value === null || $value === '')) {
			throw new InvalidArgumentException(
				"Missing required \"{$key}\" configuration for driver " . static::class . '.'
			);
		}

		return $value;
	}
}
