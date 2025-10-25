<?php

use Kirby\Http\Response;
use tobimori\Seo\Ai;

return [
	'seo-writer' => [
		'extends' => 'writer',
		'props' => [
			/**
			 * Enables/disables the character counter in the top right corner
			 */
			'ai' => function (string|bool $ai = false) {
				if (!Ai::enabled()) {
					return false;
				}

				return $ai;
			},

			// reset defaults
			'counter' => fn (bool $counter = false) => $counter, // we have to disable the counter because its at the same place as our ai button
			'inline' => fn (bool $inline = true) => $inline,
			'marks' => fn (array|bool|null $marks = false) => $marks,
			'nodes' => fn (array|bool|null $nodes = false) => $nodes,
		],
		'api' => fn () => [
			[
				'pattern' => 'ai/stream',
				'method' => 'POST',
				'action' => function () {
					if (!Ai::enabled()) {
						return Response::json([
							'status' => 'error',
							'message' => t('seo.ai.error.disabled', 'AI features are disabled.')
						], 404);
					}

					$field = $this->field();
					$model = $field->model();
					$lang = $model?->kirby()->language()?->code();
					$data = kirby()->request()->body()->data();
					$taskId = $data['task'] ?? null;

					if (!is_string($taskId) || trim($taskId) === '') {
						return Response::json([
							'status' => 'error',
							'message' => 'Missing AI task identifier.'
						], 400);
					}

					$variables = $data['variables'] ?? [];
					$context = $data['context'] ?? [];

					if (!is_array($variables)) {
						$variables = [];
					}

					if (!is_array($context)) {
						$context = [];
					}

					if ($lang) {
						kirby()->setCurrentLanguage($lang);
					}

					if ($model) {
						kirby()->site()->visit($model);
					}

					ignore_user_abort(true);
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
						echo 'data: ' . json_encode(
							$event,
							JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
						) . "\n\n";

						if (ob_get_level() > 0) {
							ob_flush();
						}

						flush();
					};

					try {
						foreach (
							Ai::streamTask($taskId, $variables, [
								...$context,
								'model' => $model,
								'language' => $lang,
							]) as $chunk
						) {
							$send([
								'type' => $chunk->type,
								'text' => $chunk->text,
								'payload' => $chunk->payload,
							]);
						}

						$send(['type' => 'stream_end']);
					} catch (\Throwable $exception) {
						$send([
							'type' => 'error',
							'payload' => [
								'message' => $exception->getMessage(),
							],
						]);
					}

					exit();
				}
			]
		]
	]
];
