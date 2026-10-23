<?php

namespace tobimori\Seo\Ai;

use Generator;
use Kirby\Exception\InvalidArgumentException;
use tobimori\Seo\Seo;

/**
 * @consistent-constructor
 */
abstract class Driver
{
	public function __construct(protected string $providerId)
	{
	}

	/**
	 * Streams a response for the given content.
	 *
	 * @param array<Content> $content Array of Content messages forming a conversation.
	 * @param string|null $model Model override.
	 *
	 * @return Generator<int, Chunk, mixed, void>
	 */
	abstract public function stream(array $content, string|null $model = null): Generator;

	/**
	 * Whether the provider has the configuration it needs to send requests.
	 * AI features are hidden in the Panel when this returns false.
	 */
	public function configured(): bool
	{
		return (string)$this->config('apiKey') !== '';
	}

	/**
	 * Returns a configuration value or throws when required.
	 */
	protected function config(string $key, mixed $default = null, bool $required = false): mixed
	{
		$value = Seo::option("ai.providers.{$this->providerId}.config.{$key}", $default);

		if ($required === true && ($value === null || $value === '')) {
			throw new InvalidArgumentException(
				"Missing required \"{$key}\" configuration for driver " . static::class . '.'
			);
		}

		return $value;
	}
}
