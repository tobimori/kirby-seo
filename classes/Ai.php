<?php

namespace tobimori\Seo;

use Generator;
use Kirby\Exception\Exception as KirbyException;
use tobimori\Seo\Ai\Driver;

/**
 * Ai facade
 */
final class Ai
{
	private static array $providers = [];

	public static function enabled(): bool
	{
		return (bool)Seo::option('ai.enabled', false);
	}

	/**
	 * Returns a provider instance for the given ID or the default provider.
	 */
	public static function provider(string|null $providerId = null): Driver
	{
		$providerId ??= Seo::option('ai.provider');

		if (isset(self::$providers[$providerId])) {
			return self::$providers[$providerId];
		}

		$config = Seo::option("ai.providers.{$providerId}");
		if (!is_array($config)) {
			throw new KirbyException("AI provider \"{$providerId}\" is not defined.");
		}

		$driver = $config['driver'] ?? null;
		if (!is_string($driver) || $driver === '') {
			throw new KirbyException("AI provider \"{$providerId}\" is missing a driver reference.");
		}

		if (!is_subclass_of($driver, Driver::class)) {
			throw new KirbyException("AI provider driver \"{$driver}\" must extend " . Driver::class . '.');
		}

		return self::$providers[$providerId] = new $driver($config['config'] ?? []);
	}

	public static function streamTask(string $taskId, array $variables = []): Generator
	{
		$snippet = "seo/prompts/tasks/{$taskId}";
		$prompt = trim(snippet($snippet, $variables, return: true));
		if ($prompt === '') {
			throw new KirbyException("AI prompt snippet \"{$snippet}\" is missing or empty.");
		}

		return self::provider()->stream($prompt, /* todo custom model here */);
	}
}
