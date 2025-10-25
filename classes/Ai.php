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
		$providerId ??= Seo::option('ai.defaultProvider');

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

	public static function streamTask(string $taskId, array $variables = [], array $context = []): Generator
	{
		$task = Seo::option("ai.tasks.{$taskId}");
		if (!is_array($task)) {
			throw new KirbyException("AI task \"{$taskId}\" is not defined.");
		}

		$promptKey = $task['prompt'] ?? null;
		if (!is_string($promptKey) || $promptKey === '') {
			throw new KirbyException("AI task \"{$taskId}\" is missing a prompt key.");
		}

		$prompt = tt($promptKey, $variables);
		$providerId = $task['provider'] ?? null;

		return self::provider($providerId)->stream($prompt, $context);
	}
}
