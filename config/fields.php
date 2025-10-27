<?php

use Kirby\Cms\App;
use Kirby\Cms\Page;
use Kirby\Http\Response;
use tobimori\Seo\Ai;
use tobimori\Seo\Seo;

return [
	'seo-writer' => [
		'extends' => 'writer',
		'props' => [
			/**
			 * Enables/disables the character counter in the top right corner
			 */
			'ai' => function (string|bool $ai = false) {
				if (!Seo::option('components.ai')::enabled()) {
					return false;
				}

				// check ai permission @see index.php L31
				if (App::instance()->user()->role()->permissions()->for('tobimori.seo', 'ai') === false) {
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
					$kirby = $this->kirby();
					$component = Seo::option('components.ai');

					if (!$component::enabled()) {
						return Response::json([
							'status' => 'error',
							'message' => t('seo.ai.error.disabled')
						], 404);
					}

					if ($kirby->user()->role()->permissions()->for('tobimori.seo', 'ai') === false) {
						return Response::json([
							'status' => 'error',
							'message' => t('seo.ai.error.permission')
						], 404);
					}

					$data = $kirby->request()->body()->data();
					$lang = $kirby->api()->language();

					// for site, use homepage
					$model = $this->field()->model();
					$page = $model instanceof Page ? $model : $model->homePage();
					$kirby->site()->visit($page, $lang);
					if ($lang) {
						$kirby->setCurrentLanguage($lang);
					}

					// inject data in snippets / rendering process
					$kirby->data = [ // TODO: check if we want to access the draft / edited version for $page
						'page' => $page,
						'site' => $kirby->site(),
						'kirby' => $kirby
					];

					// begin streaming thingy
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
							$component::streamTask($this->field()->ai(), [
								'instructions' => $data['instructions'] ?? null,
								'edit' => $data['edit'] ?? null
							]) as $chunk
						) {
							$send([
								'type' => $chunk->type,
								'text' => $chunk->text,
								'payload' => $chunk->payload,
							]);
						}
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
