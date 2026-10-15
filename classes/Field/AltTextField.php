<?php

namespace tobimori\Seo\Field;

use Closure;
use Kirby\Cms\App;
use Kirby\Cms\File;
use Kirby\Form\Field;
use Kirby\Form\Field\InputField;
use Kirby\Http\Response;
use Kirby\Reflection\Attributes\Prop;
use tobimori\Seo\Ai\Content;
use tobimori\Seo\AltText;
use tobimori\Seo\Seo;

class AltTextField extends InputField
{
	/**
	 * Shows the AI buttons, if AI is enabled & the file is a supported image
	 */
	#[Prop]
	protected bool $ai = true;

	/**
	 * Generates the alt text with AI after an upload or a file replacement
	 */
	#[Prop]
	protected bool $autogenerate = false;

	protected array $value = [];

	public function ai(): bool
	{
		$component = Seo::option('components.ai');
		$model = $this->model();

		return (
			$this->ai &&
				$component::enabled() &&
				$component::permitted() &&
				!($model instanceof File && !Content::supportsImage($model))
		);
	}

	public function autogenerate(): bool
	{
		return $this->autogenerate;
	}

	public function fill(mixed $value): static
	{
		return parent::fill(is_array($value) ? $value : AltText::parse($value)->toArray());
	}

	public function toStoredValue(): string
	{
		$altText = new AltText(
			text: $this->value['text'] ?? '',
			decorative: $this->value['decorative'] ?? false,
			source: $this->value['source'] ?? AltText::SOURCE_MANUAL
		);

		return $altText->isMissing() ? '' : $altText->toYaml();
	}

	public function api(): array
	{
		$field = $this;

		return [
			[
				'pattern' => 'ai/stream',
				'method' => 'POST',
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

					AltTextField::stream($model, $kirby->request()->body()->data()['instructions'] ?? null);
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

		$component::sendStream(function (Closure $send) use ($kirby, $file, $instructions, $component) {
			$kirby->data = [
				'file' => $file,
				'site' => $kirby->site(),
				'kirby' => $kirby
			];

			$prompt = trim(snippet(
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
			'kirby' => $kirby
		];

		$langCodes = $isMultiLang
			? $languages->pluck('code')
			: [];

		$prompt = trim(snippet(
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
