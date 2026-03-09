<?php

namespace tobimori\Seo\Field;

use Kirby\Cms\App;
use Kirby\Cms\File;
use Kirby\Form\Field;
use Kirby\Form\FieldClass;
use Kirby\Http\Response;
use tobimori\Seo\Ai\Content;
use tobimori\Seo\AltText;
use tobimori\Seo\Seo;

class AltTextField extends FieldClass
{
	protected bool $ai;
	protected bool $autogenerate;
	protected mixed $value = [];

	public function __construct(array $params = [])
	{
		$this->autogenerate = $params['autogenerate'] ?? false;
		parent::__construct($params);
		$this->setAi($params['ai'] ?? true);
	}

	public function type(): string
	{
		return 'alt-text';
	}

	protected function setAi(bool $ai = true): void
	{
		if ($ai && !Seo::option('components.ai')::enabled()) {
			$ai = false;
		}

		if ($ai && App::instance()->user()?->role()->permissions()->for('tobimori.seo', 'ai') === false) {
			$ai = false;
		}

		$this->ai = $ai;
	}

	public function ai(): bool
	{
		return $this->ai;
	}

	public function autogenerate(): bool
	{
		return $this->autogenerate;
	}

	public function fill(mixed $value): static
	{
		if (is_array($value)) {
			$this->value = $value;
		} else {
			$this->value = AltText::parse($value)->toArray();
		}

		return $this;
	}

	public function toFormValue(): mixed
	{
		return $this->value;
	}

	public function toStoredValue(): mixed
	{
		if (is_array($this->value)) {
			$altText = new AltText(
				text: $this->value['text'] ?? '',
				decorative: $this->value['decorative'] ?? false,
				source: $this->value['source'] ?? AltText::SOURCE_MANUAL,
			);

			if ($altText->isMissing()) {
				return '';
			}

			return $altText->toYaml();
		}

		return $this->value;
	}

	public function props(): array
	{
		return [
			...parent::props(),
			'ai' => $this->ai(),
			'autogenerate' => $this->autogenerate(),
		];
	}

	public function routes(): array
	{
		$field = $this;

		return [
			[
				'pattern' => 'ai/stream',
				'method' => 'POST',
				'action' => function () use ($field) {
					$kirby = App::instance();
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

					$model = $field->model();

					if (!$model instanceof File || $model->type() !== 'image') {
						return Response::json([
							'status' => 'error',
							'message' => 'Field must be on an image file.'
						], 400);
					}

					$data = $kirby->request()->body()->data();
					$lang = $kirby->api()->language();

					if ($lang) {
						$kirby->setCurrentLanguage($lang);
					}

					// begin SSE stream
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
						$kirby->data = [
							'file' => $model,
							'site' => $kirby->site(),
							'kirby' => $kirby,
						];

						$prompt = trim(snippet('seo/prompts/tasks/alt-text', [
							'file' => $model,
							'instructions' => $data['instructions'] ?? null,
						], return: true));

						$content = [
							Content::user()
								->image($model)
								->text($prompt),
						];

						foreach ($component::provider()->stream($content) as $chunk) {
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
		];
	}

	/**
	 * Generates alt text for all autogenerate-enabled fields on a file.
	 * Handles both single-lang and multi-lang sites in a single AI call.
	 */
	public static function generateForFile(File $file): File
	{
		if ($file->type() !== 'image') {
			return $file;
		}

		$component = Seo::option('components.ai');
		if (!$component::enabled()) {
			return $file;
		}

		$blueprint = $file->blueprint();
		$autogenerateFields = [];

		foreach ($blueprint->fields() as $name => $field) {
			$fieldClass = Field::$types[$field['type'] ?? ''] ?? null;
			if (!is_a($fieldClass, static::class, true)) {
				continue;
			}

			if (empty($field['autogenerate'])) {
				continue;
			}

			$autogenerateFields[] = $name;
		}

		if ($autogenerateFields === []) {
			return $file;
		}

		$kirby = $file->kirby();
		$languages = $kirby->languages();
		$isMultiLang = $languages->isNotEmpty();

		$kirby->data = [
			'file' => $file,
			'site' => $kirby->site(),
			'kirby' => $kirby,
		];

		$langCodes = $isMultiLang
			? $languages->pluck('code')
			: [];

		$prompt = trim(snippet('seo/prompts/tasks/alt-text', [
			'file' => $file,
			'languages' => $langCodes,
		], return: true));

		$content = [
			Content::user()
				->image($file)
				->text($prompt),
		];

		$text = '';
		foreach ($component::provider()->stream($content) as $chunk) {
			if ($chunk->type === 'text-delta') {
				$text .= $chunk->text;
			}
		}

		$text = trim($text);
		if ($text === '') {
			return $file;
		}

		// parse into [langCode => altText] map (single-lang uses null key)
		$results = [];

		if ($isMultiLang) {
			foreach (explode("\n", $text) as $line) {
				$line = trim($line);
				if ($line === '' || !str_contains($line, ':')) {
					continue;
				}

				$colonPos = strpos($line, ':');
				$code = trim(substr($line, 0, $colonPos));
				$value = trim(substr($line, $colonPos + 1));

				if ($value !== '' && in_array($code, $langCodes, true)) {
					$results[$code] = $value;
				}
			}
		} else {
			$results[null] = $text;
		}

		return $kirby->impersonate('kirby', function () use ($file, $results, $autogenerateFields) {
			foreach ($results as $langCode => $altText) {
				$updates = [];
				foreach ($autogenerateFields as $name) {
					$updates[$name] = (new AltText(text: $altText, source: AltText::SOURCE_AI))->toYaml();
				}

				$file = $file->update($updates, $langCode);
			}

			return $file;
		});
	}
}
