<?php

namespace tobimori\Seo;

use Closure;
use Generator;
use Kirby\Cms\App;
use Kirby\Data\Json;
use Kirby\Exception\Exception as KirbyException;
use Kirby\Http\Response;
use Kirby\Toolkit\I18n;
use Throwable;
use tobimori\Seo\Ai\Chunk;
use tobimori\Seo\Ai\Content;
use tobimori\Seo\Ai\Driver;

use function is_array;
use function is_string;

/**
 * Ai facade
 */
class Ai
{
	/** @var array<string, Driver> */
	private static array $providers = [];

	public static function enabled(): bool
	{
		return Seo::option('ai.enabled', false) && static::provider()->configured();
	}

	/**
	 * Checks the `tobimori.seo.ai` permission of the current user
	 */
	public static function permitted(): bool
	{
		return App::instance()->user()?->role()->permissions()->for('tobimori.seo', 'ai') !== false;
	}

	/**
	 * Returns an error response for API routes if AI is disabled or not permitted
	 */
	public static function denied(): Response|null
	{
		$error = match (true) {
			!static::enabled() => 'seo.ai.error.disabled',
			!static::permitted() => 'seo.ai.error.permission',
			default => null
		};

		return $error ? Response::json(['status' => 'error', 'message' => I18n::translate($error)], 404) : null;
	}

	/**
	 * Sends chunks to the Panel as server-sent events and ends the request.
	 * Errors are sent as error events.
	 *
	 * @param Closure(Closure(Chunk): void $send): void $stream
	 */
	public static function sendStream(Closure $stream): never
	{
		ignore_user_abort(true);
		// @mago-expect lint:no-error-control-operator
		@set_time_limit(0);

		while (ob_get_level() > 0) {
			ob_end_flush();
		}

		header('Content-Type: text/event-stream');
		header('Cache-Control: no-cache');
		header('Connection: keep-alive');
		header('X-Accel-Buffering: no');
		echo ":ok\n\n";
		flush();

		$send = static function (array $event): void {
			echo
				'data: ' .
					Json::encode($event) .
					"\n\n";

			if (ob_get_level() > 0) {
				ob_flush();
			}

			flush();
		};

		try {
			$stream(static fn (Chunk $chunk) => $send([
				'type' => $chunk->type,
				'text' => $chunk->text,
				'payload' => $chunk->payload
			]));
		} catch (Throwable $exception) {
			$send([
				'type' => 'error',
				'payload' => [
					'message' => $exception->getMessage()
				]
			]);
		}

		exit();
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

		return self::$providers[$providerId] = new $driver($providerId);
	}

	public static function streamTask(string $taskId, array $variables = []): Generator
	{
		$snippet = "seo/prompts/tasks/{$taskId}";
		$prompt = trim(App::instance()->snippet($snippet, $variables, return: true));
		if ($prompt === '') {
			throw new KirbyException("AI prompt snippet \"{$snippet}\" is missing or empty.");
		}

		$content = [Content::user()->text($prompt)];

		return self::provider()->stream($content);
	}
}
