<?php

namespace tobimori\Seo\Agents;

use tobimori\Agents\Content\InputCheck;
use tobimori\Agents\Content\Presenter;
use tobimori\Agents\Fields\Field;
use tobimori\Agents\Schema\Compiler;
use tobimori\Seo\AltText;

/**
 * Describes the `alt-text` field to Kirby Agents.
 * Only loaded when Kirby Agents is installed, @see index.php
 */
class AltTextField extends Field
{
	public function describe(Compiler $schema): string
	{
		return 'alt text, object {"text": string, "decorative": boolean}, or only the text as a string. '
			. 'Set `decorative: true` with an empty text for images without meaning, or null to remove the alt text. '
			. '`source` (manual, ai or reviewed) is set automatically';
	}

	public function input(mixed $value, mixed $current): mixed
	{
		$value = self::json($value);

		if (is_string($value)) {
			$value = ['text' => $value];
		}

		if (!is_array($value)) {
			return $value;
		}

		$value = [
			'text' => $value['text'] ?? '',
			'decorative' => $value['decorative'] ?? false,
		];

		// keep the source of an unchanged value, so a reviewed alt text stays reviewed
		$current = is_array($current) ? $current : [];
		$unchanged = $value['text'] === ($current['text'] ?? '')
			&& $value['decorative'] === ($current['decorative'] ?? false);

		return [
			...$value,
			'source' => $unchanged ? ($current['source'] ?? AltText::SOURCE_MANUAL) : AltText::SOURCE_AI,
		];
	}

	public function check(mixed $value, InputCheck $check, string $where): void
	{
		if ($value === null || !$check->isNew($value)) {
			return;
		}

		if (!is_array($value) || !is_string($value['text'] ?? null) || !is_bool($value['decorative'] ?? null)) {
			$check->error("{$where}: send an object {\"text\": string, \"decorative\": boolean} or a string");

			return;
		}

		// Kirby would store an empty alt text without an error
		if ($value['decorative'] === false && trim($value['text']) === '') {
			$check->error("{$where}: send a text, `decorative: true` for images without meaning, or null to remove the alt text");
		}
	}

	public function summary(mixed $value, Presenter $presenter): string
	{
		if (is_array($value) && ($value['decorative'] ?? false) === true) {
			return '(decorative)';
		}

		return Presenter::short(is_array($value) ? ($value['text'] ?? '') : '');
	}
}
