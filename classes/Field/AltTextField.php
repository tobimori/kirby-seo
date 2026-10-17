<?php

namespace tobimori\Seo\Field;

use Closure;
use Kirby\Cms\App;
use Kirby\Cms\File;
use Kirby\Form\Field;
use Kirby\Form\FieldClass;
use Kirby\Http\Response;
use Stringable;
use tobimori\Seo\Ai;
use tobimori\Seo\Ai\Content;
use tobimori\Seo\AltText;
use tobimori\Seo\Seo;

class AltTextField extends FieldClass
{
	protected bool $ai;
	protected bool $autogenerate;
	protected mixed $value = [];

	/**
	 * @param array{autogenerate?: bool, ai?: bool, ...} $params
	 */
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
		$component = Seo::option('components.ai');

		$model = $this->model();
		$this->ai =
			$ai &&
			$component::enabled() &&
			$component::permitted() &&
			!($model instanceof File && !Content::supportsImage($model));
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
			// scalars and fields are converted to strings, like before the type check
			$this->value = AltText::parse(is_scalar($value) || $value instanceof Stringable ? (string)$value : null)->toArray();
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
			/** @var array{text?: string, decorative?: bool, source?: string} $value */
			$value = $this->value;
			$altText = new AltText(
				text: $value['text'] ?? '',
				decorative: $value['decorative'] ?? false,
				source: $value['source'] ?? AltText::SOURCE_MANUAL
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
			'autogenerate' => $this->autogenerate()
		];
	}

	public function routes(): array
	{
		$field = $this;

		return [
			[
				'pattern' => 'ai/stream',
				'method' => 'POST',
				// @mago-expect lint:prefer-static-closure
				'action' => function () use ($field) {
					$kirby = App::instance();
					$component = Seo::option('components.ai');

					if ($error = $component::denied()) {
						return $error;
					}

					$model = $field->model();

					if (!$model instanceof File || $model->type() !== 'image') {
						return Response::json([
							'status' => 'error',
							'message' => 'Field must be on an image file.'
						], 400);
					}

					if (!Content::supportsImage($model)) {
						return Response::json([
							'status' => 'error',
							'message' => 'This image format requires ext-imagick.'
						], 400);
					}

					$lang = $kirby->api()->language();

					if ($lang) {
						$kirby->setCurrentLanguage($lang);
					}

					$instructions = $kirby->request()->body()->data()['instructions'] ?? null;
					AltTextField::stream($model, is_string($instructions) ? $instructions : null);
				}
			]
		];
	}

	/**
	 * Streams alt text in the current language and ends the request.
	 * Callers must check permissions before starting the stream
	 */
	public static function stream(File $file, string|null $instructions = null): never
	{
		$kirby = $file->kirby();
		$component = Seo::option('components.ai');

		$component::sendStream(static function (Closure $send) use ($kirby, $file, $instructions, $component) {
			$kirby->data = [
				'file' => $file,
				'site' => $kirby->site(),
				'kirby' => $kirby
			];

			$prompt = trim(App::instance()->snippet(
				'seo/prompts/tasks/alt-text',
				[
					'file' => $file,
					'instructions' => $instructions
				],
				return: true
			));

			$content = [
				Content::user()
					->image($file)
					->text($prompt)
			];

			foreach ($component::provider()->stream($content) as $chunk) {
				$send($chunk);
			}
		});
	}

	/**
	 * Generates alt text for all autogenerate-enabled fields on a file.
	 * Handles both single-lang and multi-lang sites in a single AI call.
	 */
	public static function generateForFile(File $file): File
	{
		if ($file->type() !== 'image' || !Content::supportsImage($file)) {
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
			if (!is_string($fieldClass) || !is_a($fieldClass, static::class, true)) {
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
			'kirby' => $kirby
		];

		$langCodes = $isMultiLang
			? $languages->pluck('code')
			: [];

		$prompt = trim(App::instance()->snippet(
			'seo/prompts/tasks/alt-text',
			[
				'file' => $file,
				'languages' => $langCodes
			],
			return: true
		));

		$content = [
			Content::user()
				->image($file)
				->text($prompt)
		];

		$text = '';
		foreach ($component::provider()->stream($content) as $chunk) {
			if ($chunk->type === 'text-delta') {
				$text .= $chunk->text ?? '';
			}
		}

		$text = trim($text);
		if ($text === '') {
			return $file;
		}

		// parse into [langCode, altText] pairs (single-lang uses the `null` language code)
		$results = [];

		if ($isMultiLang) {
			foreach (explode("\n", $text) as $line) {
				$line = trim($line);
				if ($line === '' || !str_contains($line, ':')) {
					continue;
				}

				[$code, $value] = array_map(trim(...), explode(':', $line, 2));

				if ($value !== '' && in_array($code, $langCodes, true)) {
					$results[$code] = [$code, $value];
				}
			}
		} else {
			$results[] = [null, $text];
		}

		/** @var File */
		return $kirby->impersonate('kirby', static function () use ($file, $results, $autogenerateFields) {
			foreach ($results as [$langCode, $altText]) {
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
