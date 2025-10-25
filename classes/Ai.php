<?php

namespace tobimori\Seo;

use Generator;
use Kirby\Cms\App;
use Kirby\Cms\Site;
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
		$prompt = self::renderPrompt($taskId, $variables);
		$providerId = $task['provider'] ?? null;

		return self::provider($providerId)->stream($prompt);
	}

	private static function renderPrompt(string $taskId, $model, ?string $lang): string
	{
		$snippet = "seo/prompts/tasks/{$taskId}";

		$page = $model instanceof Site ? $model->homePage() : $model;
		App::instance()->site()->visit($page, $lang);
		App::instance()->data = [
			'page' => $page,
			'site' => App::instance()->site(),
			'kirby' => App::instance(),
		];
		$content = snippet($snippet, [], return: true);

		$content = trim($content ?? '');

		if ($content !== '') {
			return $content;
		}

		throw new KirbyException("AI prompt snippet \"{$snippet}\" is missing or empty.");
	}
}
